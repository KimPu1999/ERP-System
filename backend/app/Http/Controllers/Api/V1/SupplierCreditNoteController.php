<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountMapping, CompanyMembership, FiscalPeriod, Supplier, SupplierBill, SupplierCreditNote};
use App\Services\{AccountingService, AuditLogger, AuthorizationService, ProcurementNumberService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SupplierCreditNoteController
{
    public function index(Request $request, AuthorizationService $auth): JsonResponse
    {
        $tenant = $this->tenant(); $company = $request->string('company_id')->toString() ?: null;
        $this->scope($request, $auth, $tenant, $company);
        $query = SupplierCreditNote::where('tenant_id', $tenant)->with(['supplier', 'bill', 'currency'])->when($company, fn ($q) => $q->where('company_id', $company))->latest('credit_date');
        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 10), 100))]);
    }

    public function store(Request $request, AuthorizationService $auth, ProcurementNumberService $numbers, AuditLogger $audit): JsonResponse
    {
        $tenant = $this->tenant();
        $data = $request->validate(['company_id'=>'required|uuid','branch_id'=>'nullable|uuid','supplier_id'=>'required|uuid','supplier_bill_id'=>'nullable|uuid','purchase_return_id'=>'nullable|uuid','currency_id'=>'required|uuid','credit_date'=>'required|date','reason'=>'required|string|max:2000','total'=>['required','regex:/^\d+(\.\d{1,6})?$/']]);
        $this->scope($request, $auth, $tenant, $data['company_id']);
        abort_unless(Supplier::where('tenant_id', $tenant)->whereKey($data['supplier_id'])->exists(), 404);
        if (! empty($data['purchase_return_id'])) abort_unless(\App\Models\PurchaseReturn::where('tenant_id',$tenant)->where('company_id',$data['company_id'])->whereKey($data['purchase_return_id'])->exists(),422);
        if (! empty($data['supplier_bill_id'])) abort_unless(SupplierBill::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->where('supplier_id', $data['supplier_id'])->where('state', 'posted')->whereKey($data['supplier_bill_id'])->exists(), 422);
        $note = SupplierCreditNote::create([...$data, 'tenant_id'=>$tenant, 'number'=>$numbers->make('SCN'), 'state'=>'draft', 'created_by'=>$request->user()->id]);
        $audit->record($request, 'supplier_credit.created', $tenant, $note);
        return response()->json(['data' => $note], 201);
    }

    public function post(Request $request, SupplierCreditNote $supplierCreditNote, AccountingService $accounting, AuthorizationService $auth, AuditLogger $audit): JsonResponse
    {
        $tenant = $this->tenant(); abort_unless($supplierCreditNote->tenant_id === $tenant, 404); $this->scope($request, $auth, $tenant, $supplierCreditNote->company_id); abort_unless($auth->allows($request->user(), $tenant, 'accounting.post', $supplierCreditNote->company_id, $supplierCreditNote->branch_id), 403);
        $note = DB::transaction(function () use ($supplierCreditNote, $tenant, $request, $accounting): SupplierCreditNote {
            $note = SupplierCreditNote::where('tenant_id', $tenant)->whereKey($supplierCreditNote->id)->lockForUpdate()->firstOrFail(); if ($note->state === 'posted') return $note; if ($note->state !== 'draft') throw ValidationException::withMessages(['state'=>['Only draft supplier credits can be posted.']]);
            $ap = AccountMapping::where('tenant_id', $tenant)->where('company_id', $note->company_id)->where('mapping_key', 'accounts_payable')->first(); $expense = AccountMapping::where('tenant_id', $tenant)->where('company_id', $note->company_id)->where('mapping_key', 'purchase_expense')->first(); if (! $ap || ! $expense) throw ValidationException::withMessages(['accounting'=>['Configure accounts_payable and purchase_expense mappings before posting supplier credits.']]);
            $period = FiscalPeriod::where('tenant_id', $tenant)->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $note->company_id))->whereDate('starts_on', '<=', $note->credit_date)->whereDate('ends_on', '>=', $note->credit_date)->first(); if (! $period) throw ValidationException::withMessages(['credit_date'=>['No open fiscal period is configured for this credit date.']]);
            $entry = $accounting->post(['tenant_id'=>$tenant,'company_id'=>$note->company_id,'fiscal_period_id'=>$period->id,'currency_id'=>$note->currency_id,'created_by'=>$request->user()->id,'entry_date'=>$note->credit_date->toDateString(),'source_type'=>SupplierCreditNote::class,'source_id'=>$note->id,'idempotency_key'=>'supplier-credit:'.$note->id,'description'=>'Supplier credit '.$note->number,'lines'=>[['account_id'=>$ap->account_id,'debit'=>$note->total,'credit'=>'0','description'=>'Supplier credit payable reduction'],['account_id'=>$expense->account_id,'debit'=>'0','credit'=>$note->total,'description'=>'Supplier credit adjustment']]]);
            $note->update(['state'=>'posted','posted_by'=>$request->user()->id,'posted_at'=>now(),'accounting_entry_id'=>$entry->id]); return $note->fresh();
        });
        $audit->record($request, 'supplier_credit.posted', $tenant, $note); return response()->json(['data'=>$note]);
    }
    private function scope(Request $request, AuthorizationService $auth, string $tenant, ?string $company): void { if ($company) { abort_unless($auth->allows($request->user(), $tenant, 'procurement.manage', $company) || $auth->allows($request->user(), $tenant, 'accounting.post', $company), 403); abort_unless(CompanyMembership::where('tenant_id',$tenant)->where('company_id',$company)->where('user_id',$request->user()->id)->where('status','active')->exists(), 403); } }
    private function tenant(): string { return (string) app(TenantContext::class)->id(); }
}
