<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class FeatureRequest extends Model
{
    use HasUuids;

    public const STATUSES = ['new', 'triaged', 'planned', 'in_development', 'in_testing', 'released', 'deferred', 'rejected'];

    public const TRANSITIONS = [
        'new' => ['triaged', 'rejected', 'deferred'],
        'triaged' => ['planned', 'in_development', 'rejected', 'deferred'],
        'planned' => ['in_development', 'deferred', 'rejected', 'triaged'],
        'in_development' => ['in_testing', 'planned', 'deferred'],
        'in_testing' => ['released', 'in_development', 'deferred'],
        'released' => [],
        'deferred' => ['triaged', 'rejected'],
        'rejected' => ['triaged'],
    ];

    protected $fillable = [
        'request_key', 'title', 'description', 'module', 'origin', 'business_impact', 'requester_reference',
        'priority', 'effort_estimate', 'risk', 'owner_id', 'status', 'target_release_id', 'target_release_notes',
        'linked_issues', 'release_evidence',
    ];


    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }

    protected function casts(): array
    {
        return ['origin' => 'array', 'linked_issues' => 'array', 'release_evidence' => 'array'];
    }
}
