<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'company_id', 'rule_id', 'idempotency_key', 'automation_type',
        'source_type', 'source_id', 'source_key', 'trigger_type', 'state', 'attempts',
        'last_error', 'failure_classification', 'correlation_id', 'started_at',
        'completed_at', 'duration_ms',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function rule() { return $this->belongsTo(AutomationRule::class, 'rule_id'); }
    public function company() { return $this->belongsTo(Company::class); }
    public function notifications() { return $this->hasMany(ErpNotification::class, 'automation_run_id'); }
}
