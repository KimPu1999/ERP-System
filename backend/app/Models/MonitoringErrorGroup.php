<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringErrorGroup extends Model
{
    use HasUuids;
    protected $fillable = ['fingerprint', 'exception_class', 'message', 'route', 'severity', 'occurrence_count', 'first_seen_at', 'last_seen_at', 'resolved_at', 'metadata'];
    protected function casts(): array { return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array']; }
}
