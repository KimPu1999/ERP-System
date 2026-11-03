<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class TenantSubscription extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'plan_id', 'billing_cycle', 'state', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'grace_ends_at', 'canceled_at', 'provider', 'provider_customer_id', 'provider_subscription_id', 'seat_count', 'price_snapshot', 'currency_code'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'current_period_starts_at' => 'datetime', 'current_period_ends_at' => 'datetime', 'grace_ends_at' => 'datetime', 'canceled_at' => 'datetime', 'price_snapshot' => 'decimal:6', 'seat_count' => 'integer'];
    }

    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function invoices() { return $this->hasMany(PlatformInvoice::class, 'subscription_id'); }
}
