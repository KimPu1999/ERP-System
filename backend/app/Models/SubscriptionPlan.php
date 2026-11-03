<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'description', 'currency_code', 'monthly_price', 'annual_price', 'trial_days', 'is_active', 'archived_at'];

    protected function casts(): array
    {
        return ['monthly_price' => 'decimal:6', 'annual_price' => 'decimal:6', 'trial_days' => 'integer', 'is_active' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function features() { return $this->hasMany(PlanFeature::class, 'plan_id'); }
    public function subscriptions() { return $this->hasMany(TenantSubscription::class, 'plan_id'); }
}
