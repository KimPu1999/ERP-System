<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class SupplierBill extends Model
{
    use HasUuids;
    protected $table = 'supplier_bills';
    protected $fillable = ['tenant_id', 'company_id', 'branch_id', 'supplier_id', 'purchase_order_id', 'currency_id', 'currency_code', 'exchange_rate', 'number', 'supplier_reference', 'invoice_date', 'accounting_date', 'due_date', 'payment_terms', 'description', 'state', 'approval_status', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'total', 'accounting_entry_id', 'created_by', 'approved_by', 'posted_by', 'approved_at', 'posted_at', 'posting_idempotency_key'];
    protected function casts(): array { return ['invoice_date' => 'date', 'accounting_date' => 'date', 'due_date' => 'date', 'exchange_rate' => 'decimal:12', 'subtotal' => 'decimal:6', 'discount_total' => 'decimal:6', 'tax_total' => 'decimal:6', 'shipping_total' => 'decimal:6', 'grand_total' => 'decimal:6', 'total' => 'decimal:6', 'approved_at' => 'datetime', 'posted_at' => 'datetime']; }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function company() { return $this->belongsTo(Company::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function currency() { return $this->belongsTo(AccountingCurrency::class, 'currency_id'); }
    public function lines() { return $this->hasMany(SupplierBillLine::class); }
    public function matches() { return $this->hasMany(SupplierBillMatch::class); }
    public function payments() { return $this->hasManyThrough(SupplierPayment::class, SupplierPaymentAllocation::class, 'supplier_bill_id', 'id', 'id', 'supplier_payment_id'); }
    public function allocations() { return $this->hasMany(SupplierPaymentAllocation::class); }
    public function accountingEntry() { return $this->belongsTo(JournalEntry::class, 'accounting_entry_id'); }
}
