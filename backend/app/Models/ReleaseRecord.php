<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReleaseRecord extends Model
{
    use HasUuids;

    /** Legacy Phase 12 statuses plus the Phase 16 lifecycle. */
    public const STATUSES = ['draft', 'ready_for_qa', 'qa_passed', 'approved', 'deploying', 'deployed', 'failed', 'rolled_back', 'aborted'];

    public const TRANSITIONS = [
        'draft' => ['ready_for_qa', 'aborted'],
        'ready_for_qa' => ['qa_passed', 'failed'],
        'qa_passed' => ['approved', 'failed'],
        'approved' => ['deploying', 'draft'],
        'deploying' => ['deployed', 'failed', 'rolled_back'],
        'deployed' => ['rolled_back'],
        'failed' => ['draft', 'rolled_back'],
        'rolled_back' => ['draft'],
    ];

    protected $fillable = [
        'version', 'environment', 'status', 'release_notes', 'release_notes_my', 'release_notes_sections',
        'known_issues', 'rollback_reference', 'deployed_at', 'recorded_by', 'release_id', 'linked_features',
        'linked_fixes', 'audience', 'customer_visible', 'published_at', 'approval_state', 'approved_by', 'approved_at', 'deployment_ref',
    ];

    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime', 'published_at' => 'datetime', 'approved_at' => 'datetime',
            'linked_features' => 'array', 'linked_fixes' => 'array', 'release_notes_sections' => 'array',
            'customer_visible' => 'boolean',
        ];
    }
}
