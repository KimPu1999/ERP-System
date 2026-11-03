<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringAlert extends Model
{
    use HasUuids;
    protected $fillable = ['fingerprint', 'service', 'severity', 'title', 'description', 'status', 'first_observed_at', 'last_observed_at', 'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at', 'metadata'];
    protected function casts(): array { return ['first_observed_at' => 'datetime', 'last_observed_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array']; }
    public function acknowledgedBy() { return $this->belongsTo(User::class, 'acknowledged_by'); }
    public function resolvedBy() { return $this->belongsTo(User::class, 'resolved_by'); }
}
