<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringObservation extends Model
{
    use HasUuids;
    protected $fillable = ['service', 'check_key', 'status', 'observed_at', 'response_time_ms', 'detail', 'metadata'];
    protected function casts(): array { return ['observed_at' => 'datetime', 'metadata' => 'array']; }
}
