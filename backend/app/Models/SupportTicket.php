<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SupportTicket extends Model
{
    use HasUuids;

    public const STATUSES = ['open', 'in_progress', 'waiting_customer', 'waiting_support', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const CATEGORIES = ['general', 'billing', 'account_access', 'inventory', 'sales', 'purchasing', 'accounting', 'integration', 'data_import'];

    /** Valid lifecycle transitions (self is always allowed as a no-op guard). */
    public const TRANSITIONS = [
        'open' => ['in_progress', 'waiting_customer', 'resolved', 'closed'],
        'in_progress' => ['open', 'waiting_customer', 'waiting_support', 'resolved', 'closed'],
        'waiting_customer' => ['in_progress', 'resolved', 'closed'],
        'waiting_support' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['closed', 'open'], // open = reopen
        'closed' => ['open'], // reopen restricted to the documented window (SupportTicketService::REOPEN_WINDOW_DAYS)
    ];

    protected $table = 'support_tickets';
    protected $fillable = [
        'tenant_id', 'ticket_number', 'created_by', 'category', 'subject', 'description', 'priority',
        'assigned_to', 'status', 'sla_policy_id', 'first_response_due_at', 'resolution_due_at',
        'first_response_at', 'resolved_at', 'closed_at', 'resolved_by', 'sla_breach_log',
    ];
    protected $casts = [
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_breach_log' => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function requester() { return $this->belongsTo(User::class, 'created_by'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages() { return $this->hasMany(SupportMessage::class, 'ticket_id'); }
    public function attachments() { return $this->hasMany(SupportAttachment::class, 'ticket_id'); }
    public function slaPolicy() { return $this->belongsTo(SupportSlaPolicy::class, 'sla_policy_id'); }
}
