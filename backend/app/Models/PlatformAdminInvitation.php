<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class PlatformAdminInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'inviter_user_id', 'email', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at', 'status',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }
}
