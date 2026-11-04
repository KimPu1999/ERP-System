<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class RoadmapItem extends Model
{
    use HasUuids;

    public const STATUSES = ['backlog', 'planned', 'in_progress', 'in_testing', 'ready_for_release', 'released'];

    protected $fillable = [
        'title', 'description', 'module', 'source_type', 'source_id', 'priority', 'owner_id', 'status',
        'dependencies', 'target_release_id', 'target_quarter', 'completion_criteria', 'release_evidence',
        'visibility', 'customer_summary',
    ];


    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }

    protected function casts(): array
    {
        return ['dependencies' => 'array', 'release_evidence' => 'array'];
    }
}
