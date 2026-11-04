<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class DefectRecord extends Model
{
    use HasUuids;

    public const SEVERITIES = ['P0', 'P1', 'P2', 'P3'];

    public const STATUSES = ['open', 'triaged', 'assigned', 'in_progress', 'fixed', 'ready_for_retest', 'verified', 'closed'];

    public const TRANSITIONS = [
        'open' => ['triaged', 'assigned'],
        'triaged' => ['assigned', 'in_progress', 'open'],
        'assigned' => ['in_progress', 'triaged', 'open'],
        'in_progress' => ['fixed', 'triaged', 'assigned'],
        'fixed' => ['ready_for_retest', 'in_progress'],
        'ready_for_retest' => ['verified', 'in_progress', 'open'], // regression failure reopens
        'verified' => ['closed', 'ready_for_retest'],
        'closed' => ['open'], // reopen only allowed with a regression reason
    ];

    protected $fillable = [
        'defect_number', 'title', 'module', 'environment', 'severity', 'reproduction_steps', 'expected_result',
        'actual_result', 'error_summary', 'root_cause', 'assignee_id', 'status', 'related_change_ref',
        'related_test_ref', 'fix_release_id', 'fix_version', 'retest_result', 'retest_verified_by',
        'retest_verified_at', 'reopened_count', 'source_feedback_id', 'source_incident_id', 'source_ticket_id', 'closed_at',
    ];


    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }

    protected function casts(): array
    {
        return ['retest_verified_at' => 'datetime', 'closed_at' => 'datetime', 'reopened_count' => 'integer'];
    }
}
