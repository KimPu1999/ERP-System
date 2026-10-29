<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    use HasUuids;
    protected $fillable = ['actor_user_id', 'tenant_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address'];
    protected function casts(): array { return ['metadata' => 'array']; }
}
