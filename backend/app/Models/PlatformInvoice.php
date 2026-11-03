<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class PlatformInvoice extends Model
{
    use HasUuids;
    protected $fillable = ['tenant_id', 'subscription_id', 'billing_period_key', 'number', 'status', 'currency_code', 'subtotal', 'tax_total', 'total', 'paid_total', 'issued_on', 'due_on'];
    protected function casts(): array { return ['subtotal' => 'decimal:6', 'tax_total' => 'decimal:6', 'total' => 'decimal:6', 'paid_total' => 'decimal:6', 'issued_on' => 'date', 'due_on' => 'date']; }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function subscription() { return $this->belongsTo(TenantSubscription::class, 'subscription_id'); }
    public function items() { return $this->hasMany(PlatformInvoiceItem::class, 'invoice_id'); }
    public function payments() { return $this->hasMany(PlatformPayment::class, 'invoice_id'); }
}
