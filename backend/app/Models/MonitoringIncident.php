<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringIncident extends Model
{
    use HasUuids;
    protected $fillable = ['title', 'description', 'severity', 'status', 'affected_services', 'started_at', 'acknowledged_at', 'resolved_at', 'acknowledged_by', 'resolved_by', 'resolution_notes'];
    protected function casts(): array { return ['affected_services' => 'array', 'started_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function events() { return $this->hasMany(MonitoringIncidentEvent::class, 'incident_id'); }
}
