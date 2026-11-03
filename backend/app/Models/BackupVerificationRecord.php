<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class BackupVerificationRecord extends Model
{
    use HasUuids;

    /**
     * Registered backup evidence only. A row is NOT proof of a working backup until
     * verification_status = 'restore_verified', which requires an executed restore drill
     * in an isolated environment (never against production).
     */
    protected $fillable = ['backup_taken_at', 'backup_type', 'storage_location', 'checksum', 'size_bytes', 'notes', 'verification_status', 'restore_tested_at', 'recorded_by'];
    protected function casts(): array { return ['backup_taken_at' => 'datetime', 'restore_tested_at' => 'datetime']; }
}
