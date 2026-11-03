<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class MaintenanceWindow extends Model
{
    use HasUuids;

    public const STATUSES = ['planned', 'approved', 'in_progress', 'completed', 'cancelled'];
    public const TRANSITIONS = [
        'planned' => ['approved', 'cancelled'],
        'approved' => ['in_progress', 'cancelled', 'planned'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    protected $fillable = ['title', 'scope', 'impact_description', 'rollback_plan', 'status', 'notify_businesses', 'starts_at', 'ends_at', 'created_by', 'approved_by', 'approved_at'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'approved_at' => 'datetime', 'notify_businesses' => 'boolean']; }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
