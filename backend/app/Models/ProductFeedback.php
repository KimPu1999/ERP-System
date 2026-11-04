<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ProductFeedback extends Model
{
    use HasUuids;

    public const CATEGORIES = ['bug', 'feature_request', 'usability', 'performance', 'documentation', 'other'];

    public const STATUSES = ['submitted', 'under_review', 'needs_information', 'accepted', 'planned', 'declined', 'resolved', 'closed'];

    /** Justified transitions only; arbitrary status edits are rejected by the controller. */
    public const TRANSITIONS = [
        'submitted' => ['under_review', 'declined', 'resolved', 'closed'], // closed is used for audited duplicate marking
        'under_review' => ['needs_information', 'accepted', 'planned', 'declined', 'resolved'],
        'needs_information' => ['under_review', 'declined', 'closed'],
        'accepted' => ['planned', 'declined', 'resolved', 'under_review'],
        'planned' => ['resolved', 'declined', 'closed', 'accepted'],
        'declined' => ['closed', 'under_review'],
        'resolved' => ['closed'],
        'closed' => [],
    ];

    protected $fillable = [
        'tenant_id', 'feedback_number', 'category', 'module', 'subject', 'message', 'status', 'attachments',
        'content_hash', 'duplicate_of_id', 'support_ticket_id', 'submitted_by', 'assigned_to', 'source',
        'satisfaction_score', 'satisfaction_comment', 'resolution_outcome', 'resolution_recorded_by',
    ];


    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function duplicateOf() { return $this->belongsTo(ProductFeedback::class, 'duplicate_of_id'); }

    protected function casts(): array
    {
        return ['attachments' => 'array', 'satisfaction_score' => 'integer'];
    }
}
