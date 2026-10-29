<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'tenant_id', 'event', 'ip_address', 'user_agent', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
}
