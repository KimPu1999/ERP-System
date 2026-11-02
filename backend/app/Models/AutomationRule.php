<?php
namespace App\Models; use Illuminate\Database\Eloquent\Concerns\HasUuids; use Illuminate\Database\Eloquent\Model;
class AutomationRule extends Model
{
    use HasUuids;

    public const SUPPLIER_ALLOWLIST = [
        'supplier_onboarding_reminder',
        'supplier_missing_document_reminder',
        'supplier_risk_alert',
        'supplier_overdue_bill_reminder',
        'supplier_purchase_approval_reminder',
        'supplier_hold_review_alert',
    ];

    protected $fillable=['tenant_id','company_id','branch_id','event_key','rule_type','name','conditions','actions','frequency','timezone','reminder_delay_minutes','recipients','channels','created_by','is_active'];
    protected $appends = ['automation_type', 'configuration'];
    protected function casts():array{return ['conditions'=>'array','actions'=>'array','recipients'=>'array','channels'=>'array','is_active'=>'boolean','reminder_delay_minutes'=>'integer'];}
    public function getAutomationTypeAttribute(): string { return (string) $this->event_key; }
    public function getConfigurationAttribute(): array { return (array) ($this->conditions ?? []); }
}
