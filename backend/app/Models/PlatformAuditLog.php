<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLog extends Model
{
    use HasUuids;
    protected $fillable = ['actor_user_id', 'tenant_id', 'action', 'category', 'result', 'request_id', 'actor_type', 'resource_type', 'auditable_type', 'auditable_id', 'metadata', 'before_state', 'after_state', 'ip_address'];
    protected function casts(): array { return ['metadata' => 'array', 'before_state' => 'array', 'after_state' => 'array']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Audit events are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Audit events are immutable.'));
    }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
