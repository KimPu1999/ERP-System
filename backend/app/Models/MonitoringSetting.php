<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringSetting extends Model
{
    use HasUuids;
    protected $fillable = ['setting_key', 'enabled', 'thresholds', 'notification_preferences', 'retention_days', 'refresh_seconds'];
    protected function casts(): array { return ['enabled' => 'boolean', 'thresholds' => 'array', 'notification_preferences' => 'array']; }
}
