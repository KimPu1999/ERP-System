<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MonitoringIncidentEvent extends Model
{
    use HasUuids;
    protected $fillable = ['incident_id', 'actor_user_id', 'event', 'notes', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
