<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ServiceLevelObjective extends Model
{
    use HasUuids;

    public const SLI_TYPES = ['availability', 'success_ratio', 'latency', 'queue_completion'];

    protected $fillable = [
        'name', 'sli_type', 'metric_source', 'target', 'window_days', 'criteria', 'status', 'is_active',
        'approved_by', 'approved_at', 'description',
    ];


    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    protected function casts(): array
    {
        return ['criteria' => 'array', 'is_active' => 'boolean', 'approved_at' => 'datetime', 'target' => 'decimal:3'];
    }
}
