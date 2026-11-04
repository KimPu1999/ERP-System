<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 16 governance extension. Flags gate PRESENTATION/rollout only — every
 * protected operation still checks authentication, membership, RBAC,
 * subscription entitlements, ownership and tenant scope server-side.
 */
final class FeatureFlag extends Model
{
    use HasUuids;

    protected $fillable = [
        'key', 'description', 'default_enabled', 'scope', 'rollout_stage', 'owner_id', 'review_at', 'is_sensitive',
    ];

    protected function casts(): array
    {
        return ['default_enabled' => 'boolean', 'review_at' => 'date', 'is_sensitive' => 'boolean'];
    }
}
