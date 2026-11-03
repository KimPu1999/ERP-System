<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class AuditLogArchive extends Model
{
    protected $primaryKey = 'audit_log_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['audit_log_id', 'archive_hash', 'payload', 'archived_at'];
    protected function casts(): array { return ['payload' => 'array', 'archived_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Audit archives are immutable.'));
        static::deleting(fn (): never => throw new \LogicException('Audit archives are immutable.'));
    }
}
