<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeatureRollout extends Model
{
    use HasUuids;

    public const STAGES = ['development', 'internal_cohort', 'pilot', 'wider_rollout', 'general_availability', 'disabled', 'rolled_back'];

    protected $fillable = [
        'feature_flag_id', 'stage', 'scope', 'owner_id', 'audience', 'rollout_percentage', 'approval_status',
        'approved_by', 'approved_at', 'review_at', 'monitoring_link', 'is_active',
    ];


    public function flag() { return $this->belongsTo(FeatureFlag::class, 'feature_flag_id'); }

    protected function casts(): array
    {
        return ['audience' => 'array', 'approved_at' => 'datetime', 'review_at' => 'date', 'is_active' => 'boolean'];
    }
}
