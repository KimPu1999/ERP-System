<?php

namespace App\Services;

use App\Models\{AccountMapping, Branch, Company, FiscalPeriod, GoodsReceiptItem, PurchaseOrder, SupplierBill, SupplierBillLine};
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierBillService
{
    public function createDraft(string $tenantId, string $actorId, array $data): SupplierBill
    {
        return DB::transaction(function () use ($tenantId, $actorId, $data): SupplierBill {
            $this->assertReferences($tenantId, $data);
            $this->assertDuplicateFree($tenantId, $data['company_id'], $data['supplier_id'], $data['supplier_reference'] ?? null);
            $bill = SupplierBill::create([
                ...$this->header($data), 'tenant_id' => $tenantId, 'created_by' => $actorId,
                'number' => $data['number'] ?? app(ProcurementNumberService::class)->make('BILL'),
                'state' => 'draft', 'approval_status' => 'draft',
            ]);
            $this->replaceLines($bill, $data['lines'] ?? []);
            return $bill->fresh(['supplier', 'company', 'branch', 'purchaseOrder', 'lines.variant.product', 'lines.purchaseOrderItem', 'lines.goodsReceiptItem']);
        });
    }

    public function updateDraft(SupplierBill $bill, array $data): SupplierBill
    {
        if ($bill->state !== 'draft') throw ValidationException::withMessages(['state' => ['Only draft supplier bills can be edited.']]);
        return DB::transaction(function () use ($bill, $data): SupplierBill {
            $this->assertReferences($bill->tenant_id, [...$bill->toArray(), ...$data]);
            $this->assertDuplicateFree($bill->tenant_id, $data['company_id'] ?? $bill->company_id, $data['supplier_id'] ?? $bill->supplier_id, $data['supplier_reference'] ?? $bill->supplier_reference, $bill->id);
            $bill->update($this->header($data, $bill));
            $this->replaceLines($bill, $data['lines'] ?? []);
            return $bill->fresh(['supplier', 'company', 'branch', 'purchaseOrder', 'lines.variant.product', 'lines.purchaseOrderItem', 'lines.goodsReceiptItem']);
        });
    }

    public function match(SupplierBill $bill): SupplierBill
    {
        return DB::transaction(function () use ($bill): SupplierBill {
            $bill->load('lines.purchaseOrderItem.purchaseOrder', 'lines.goodsReceiptItem.receipt', 'purchaseOrder');
            $bill->matches()->delete();
            foreach ($bill->lines as $line) {
                $status = 'matched'; $reason = null; $eligible = null; $type = 'unmatched';
                if ($line->purchase_order_item_id) {
                    $poi = $line->purchaseOrderItem;
                    abort_unless($poi && $poi->tenant_id === $bill->tenant_id && $poi->purchaseOrder?->supplier_id === $bill->supplier_id, 422, 'The purchase order line does not belong to this supplier bill.');
                    $type = $line->goods_receipt_item_id ? 'three_way' : 'two_way';
                    $prior = $this->postedBilledQuantity($bill->tenant_id, $poi->id, $bill->id);
                    $available = $line->goods_receipt_item_id ? $this->acceptedReceivedQuantity($bill->tenant_id, $poi->id) : (string) $poi->ordered_quantity;
                    $eligible = Decimal::sub($available, $prior);
                    if (Decimal::compare($line->quantity, $eligible) > 0) { $status = 'exception'; $reason = "Billed quantity exceeds remaining eligible quantity ({$eligible})."; }
                    if ($bill->purchaseOrder && $bill->purchaseOrder->currency_id && $bill->currency_id && $bill->purchaseOrder->currency_id !== $bill->currency_id) { $status = 'exception'; $reason = 'Purchase order and supplier bill currencies do not match.'; }
                    if (Decimal::compare((string) $line->unit_price, (string) $poi->unit_price) !== 0 && $status === 'matched') { $status = 'exception'; $reason = 'Unit price differs from the purchase order.'; }
                } else {
                    $status = 'pending_review'; $reason = 'This service or non-PO line requires an authorized review before posting.';
                }
                $bill->matches()->create(['tenant_id' => $bill->tenant_id, 'supplier_bill_line_id' => $line->id, 'match_type' => $type, 'status' => $status, 'eligible_quantity' => $eligible ?? '0', 'billed_quantity' => $line->quantity, 'reason' => $reason]);
            }
            return $bill->fresh(['matches.line', 'lines']);
        });
    }

    public function submit(SupplierBill $bill): SupplierBill
    {
        if ($bill->state !== 'draft') throw ValidationException::withMessages(['state' => ['Only draft bills can be submitted.']]);
        $bill->load('matches');
        if ($bill->matches->isEmpty()) $this->match($bill);
        $bill->load('matches');
        if ($bill->matches->contains(fn ($match) => $match->status !== 'matched')) throw ValidationException::withMessages(['matching' => ['The bill has matching exceptions and requires review before submission.']]);
        $bill->update(['state' => 'submitted', 'approval_status' => 'pending']);
        return $bill->fresh();
    }

    public function approve(SupplierBill $bill, string $actorId): SupplierBill
    {
        if ($bill->state !== 'submitted') throw ValidationException::withMessages(['state' => ['Only submitted bills can be approved.']]);
        $bill->load('matches');
        if ($bill->matches->isEmpty() || $bill->matches->contains(fn ($match) => $match->status !== 'matched')) throw ValidationException::withMessages(['matching' => ['Only matched bills can be approved.']]);
        $bill->update(['state' => 'approved', 'approval_status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
        return $bill->fresh();
    }

    public function post(SupplierBill $bill, string $tenantId, string $actorId, AccountingService $accounting): SupplierBill
    {
        return DB::transaction(function () use ($bill, $tenantId, $actorId, $accounting): SupplierBill {
            $bill = SupplierBill::where('tenant_id', $tenantId)->whereKey($bill->id)->lockForUpdate()->with(['lines', 'matches'])->firstOrFail();
            if ($bill->state === 'posted') return $bill;
            if ($bill->state !== 'approved') throw ValidationException::withMessages(['state' => ['Only approved bills can be posted.']]);
            if ($bill->matches->isEmpty() || $bill->matches->contains(fn ($match) => $match->status !== 'matched')) throw ValidationException::withMessages(['matching' => ['The bill must have a successful match before posting.']]);
            if (! $bill->currency_id || ! $bill->company_id) throw ValidationException::withMessages(['currency_id' => ['A company currency is required before posting.']]);
            $ap = AccountMapping::where('tenant_id', $tenantId)->where('company_id', $bill->company_id)->where('mapping_key', 'accounts_payable')->first();
            if (! $ap) throw ValidationException::withMessages(['accounting' => ['Configure the company accounts_payable account mapping before posting supplier bills.']]);
            $lines = []; $tax = '0.000000';
            foreach ($bill->lines as $line) {
                if (! $line->account_id) throw ValidationException::withMessages(['accounting' => ['Every supplier bill line requires an expense, inventory, or asset account before posting.']]);
                $net = Decimal::sub(Decimal::mul((string) $line->quantity, (string) $line->unit_price), (string) $line->discount_amount);
                $lines[] = ['account_id' => $line->account_id, 'debit' => $net, 'credit' => '0', 'description' => $line->description];
                $tax = Decimal::add($tax, (string) $line->tax_amount);
            }
            if (Decimal::compare($tax, '0') > 0) {
                $inputTax = AccountMapping::where('tenant_id', $tenantId)->where('company_id', $bill->company_id)->where('mapping_key', 'input_tax')->first();
                if (! $inputTax) throw ValidationException::withMessages(['accounting' => ['Configure the input_tax account mapping before posting a tax-bearing supplier bill.']]);
                $lines[] = ['account_id' => $inputTax->account_id, 'debit' => $tax, 'credit' => '0', 'description' => 'Recoverable input tax'];
            }
            $lines[] = ['account_id' => $ap->account_id, 'debit' => '0', 'credit' => $bill->grand_total ?: $bill->total, 'description' => 'Accounts payable'];
            $period = FiscalPeriod::where('tenant_id', $tenantId)->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $bill->company_id))->whereDate('starts_on', '<=', $bill->accounting_date ?: now())->whereDate('ends_on', '>=', $bill->accounting_date ?: now())->first();
            if (! $period) throw ValidationException::withMessages(['accounting_date' => ['No fiscal period is configured for the bill accounting date.']]);
            $entry = $accounting->post(['tenant_id' => $tenantId, 'company_id' => $bill->company_id, 'fiscal_period_id' => $period->id, 'currency_id' => $bill->currency_id, 'created_by' => $actorId, 'entry_date' => ($bill->accounting_date ?: now())->toDateString(), 'source_type' => SupplierBill::class, 'source_id' => $bill->id, 'idempotency_key' => 'supplier-bill:'.$bill->id, 'exchange_rate' => $bill->exchange_rate ?: '1', 'description' => 'Supplier bill '.$bill->number, 'lines' => $lines]);
            $bill->update(['state' => 'posted', 'approval_status' => 'posted', 'accounting_entry_id' => $entry->id, 'posted_by' => $actorId, 'posted_at' => now(), 'posting_idempotency_key' => 'supplier-bill:'.$bill->id]);
            return $bill->fresh(['accountingEntry', 'lines']);
        });
    }

    private function header(array $data, ?SupplierBill $bill = null): array
    {
        return collect($data)->only(['company_id','branch_id','supplier_id','purchase_order_id','currency_id','currency_code','exchange_rate','supplier_reference','invoice_date','accounting_date','due_date','payment_terms','description'])->filter(fn ($value) => $value !== null)->all();
    }
    private function assertReferences(string $tenantId, array $data): void
    {
        $companyId = $data['company_id'] ?? null;
        if (! $companyId || ! Company::where('tenant_id', $tenantId)->whereKey($companyId)->exists()) throw ValidationException::withMessages(['company_id' => ['The selected company is not available in the active tenant.']]);
        if (! empty($data['branch_id']) && ! Branch::where('tenant_id', $tenantId)->where('company_id', $companyId)->whereKey($data['branch_id'])->exists()) throw ValidationException::withMessages(['branch_id' => ['The selected branch does not belong to the selected company.']]);
        if (! empty($data['currency_id']) && ! \App\Models\AccountingCurrency::where('tenant_id', $tenantId)->whereKey($data['currency_id'])->exists()) throw ValidationException::withMessages(['currency_id' => ['The selected currency is not available in the active tenant.']]);
        if (! empty($data['purchase_order_id'])) {
            $order = PurchaseOrder::where('tenant_id', $tenantId)->whereKey($data['purchase_order_id'])->first();
            if (! $order || $order->company_id !== $companyId || $order->supplier_id !== ($data['supplier_id'] ?? $order->supplier_id)) throw ValidationException::withMessages(['purchase_order_id' => ['The purchase order must belong to the selected company and supplier.']]);
        }
        foreach (($data['lines'] ?? []) as $line) {
            if (! empty($line['purchase_order_item_id'])) {
                $item = \App\Models\PurchaseOrderItem::where('tenant_id', $tenantId)->whereKey($line['purchase_order_item_id'])->with('purchaseOrder')->first();
                if (! $item || $item->purchaseOrder?->company_id !== $companyId || (! empty($data['purchase_order_id']) && $item->purchase_order_id !== $data['purchase_order_id'])) throw ValidationException::withMessages(['lines' => ['A purchase order line is outside the selected company or order.']]);
            }
            if (! empty($line['goods_receipt_item_id'])) {
                $receiptLine = GoodsReceiptItem::where('tenant_id', $tenantId)->whereKey($line['goods_receipt_item_id'])->with('receipt')->first();
                if (! $receiptLine || $receiptLine->receipt?->company_id !== $companyId || ($receiptLine->receipt?->purchase_order_id !== ($data['purchase_order_id'] ?? null))) throw ValidationException::withMessages(['lines' => ['A goods receipt line is outside the selected company or purchase order.']]);
            }
        }
    }
    private function replaceLines(SupplierBill $bill, array $lines): void
    {
        if (! $lines) throw ValidationException::withMessages(['lines' => ['At least one supplier bill line is required.']]);
        $subtotal='0.000000'; $discount='0.000000'; $tax='0.000000'; $total='0.000000';
        $bill->lines()->delete();
        foreach ($lines as $line) {
            $quantity=Decimal::normalize((string)($line['quantity']??'0')); $price=Decimal::normalize((string)($line['unit_price']??'0')); $discountLine=Decimal::normalize((string)($line['discount_amount']??'0')); $taxLine=Decimal::normalize((string)($line['tax_amount']??'0')); if(Decimal::compare($quantity,'0')<=0||Decimal::compare($price,'0')<0||Decimal::compare($discountLine,'0')<0||Decimal::compare($taxLine,'0')<0) throw ValidationException::withMessages(['lines'=>['Line quantities, prices, discounts, and taxes must be valid non-negative decimals.']]);
            $net=Decimal::sub(Decimal::mul($quantity,$price),$discountLine); if(Decimal::compare($net,'0')<0) throw ValidationException::withMessages(['lines'=>['A line discount cannot exceed its line amount.']]); $lineTotal=Decimal::add($net,$taxLine);
            $bill->lines()->create([...collect($line)->only(['purchase_order_item_id','goods_receipt_item_id','variant_id','account_id','description','unit'])->all(),'tenant_id'=>$bill->tenant_id,'quantity'=>$quantity,'unit_price'=>$price,'discount_amount'=>$discountLine,'tax_amount'=>$taxLine,'line_total'=>$lineTotal]); $subtotal=Decimal::add($subtotal,Decimal::mul($quantity,$price)); $discount=Decimal::add($discount,$discountLine); $tax=Decimal::add($tax,$taxLine); $total=Decimal::add($total,$lineTotal);
        }
        $bill->update(['subtotal'=>$subtotal,'discount_total'=>$discount,'tax_total'=>$tax,'shipping_total'=>$bill->shipping_total ?: '0','grand_total'=>Decimal::add($total,(string)($bill->shipping_total ?: '0')),'total'=>Decimal::add($total,(string)($bill->shipping_total ?: '0'))]);
    }
    private function assertDuplicateFree(string $tenantId, ?string $companyId, string $supplierId, ?string $reference, ?string $ignore = null): void { if ($reference && SupplierBill::where('tenant_id',$tenantId)->where('company_id',$companyId)->where('supplier_id',$supplierId)->where('supplier_reference',$reference)->whereNotIn('state',['cancelled','reversed'])->when($ignore,fn($q)=>$q->where('id','!=',$ignore))->exists()) throw ValidationException::withMessages(['supplier_reference'=>['This supplier invoice reference already exists for this company and supplier.']]); }
    private function postedBilledQuantity(string $tenantId, string $purchaseOrderItemId, string $ignoreBillId): string { return (string) SupplierBillLine::where('tenant_id',$tenantId)->where('purchase_order_item_id',$purchaseOrderItemId)->whereHas('bill',fn($q)=>$q->where('state','posted')->where('id','!=',$ignoreBillId))->sum('quantity'); }
    private function acceptedReceivedQuantity(string $tenantId, string $purchaseOrderItemId): string { return (string) GoodsReceiptItem::where('tenant_id',$tenantId)->where('purchase_order_item_id',$purchaseOrderItemId)->whereHas('receipt',fn($q)=>$q->where('state','posted'))->sum('accepted_quantity'); }
}
