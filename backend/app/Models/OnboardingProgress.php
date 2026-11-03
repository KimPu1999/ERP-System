<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class OnboardingProgress extends Model
{
    use HasUuids;

    protected $table = 'onboarding_progress';
    protected $fillable = ['tenant_id', 'step_key', 'status', 'completed_at', 'completed_by', 'source_type', 'source_id', 'schema_version'];
    protected function casts(): array { return ['completed_at' => 'datetime']; }
}
