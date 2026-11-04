<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class MaturityAssessment extends Model
{
    use HasUuids;

    public const DIMENSIONS = [
        'functional_reliability', 'security', 'data_integrity', 'tenant_isolation', 'test_coverage',
        'accessibility', 'localization', 'documentation', 'operational_readiness', 'performance',
        'release_quality', 'customer_support',
    ];

    public const LEVELS = ['not_assessed', 'initial', 'developing', 'managed', 'optimized'];

        public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }

    protected $fillable = [
        'dimension', 'period', 'level', 'evidence', 'gaps', 'improvement_actions', 'reviewer_id', 'assessed_at',
    ];

    protected function casts(): array { return ['assessed_at' => 'datetime']; }
}
