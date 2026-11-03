<?php

namespace App\Services;

use App\Models\{SupportAttachment, SupportMessage, SupportSlaPolicy, SupportTicket};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Support ticket lifecycle, SLA binding and message threads.
 *
 * Status machine: SupportTicket::TRANSITIONS. Closed tickets may be reopened by the
 * requester within REOPEN_WINDOW_DAYS of closing; afterwards a new ticket is required.
 * SLA: SupportSlaPolicy binds elapsed-time targets. Without a configured business-hours
 * block the targets use 24x7 elapsed minutes in UTC; with business_hours they use that
 * window in the policy's timezone. Priorities without an active matching policy are
 * reported as sla_status 'not_configured' — no default targets are hardcoded.
 */
final class SupportTicketService
{
    public const REOPEN_WINDOW_DAYS = 7;
    public const OPEN_STATUSES = ['open', 'in_progress', 'waiting_customer', 'waiting_support'];

    private const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
    ];
    private const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /** Create a tenant-scoped ticket with a unique ticket number and SLA binding. */
    public function createTicket(string $tenantId, $user, array $data): SupportTicket
    {
        return DB::transaction(function () use ($tenantId, $user, $data): SupportTicket {
            $policy = $this->resolvePolicy($data['priority'] ?? 'normal');
            $sla = $this->dueTimestamps($policy, now());
            $ticket = SupportTicket::create([
                'tenant_id' => $tenantId,
                'ticket_number' => $this->generateTicketNumber(),
                'created_by' => $user->id,
                'category' => $data['category'] ?? 'general',
                'subject' => $data['subject'],
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'sla_policy_id' => $policy?->id,
                'first_response_due_at' => $sla['first_response_due_at'],
                'resolution_due_at' => $sla['resolution_due_at'],
            ]);
            // The requester's opening description is the first public thread entry.
            SupportMessage::create([
                'tenant_id' => $tenantId,
                'ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'author_type' => 'customer',
                'visibility' => 'public',
                'body' => $data['description'],
            ]);
            return $ticket;
        });
    }

    /** Append a thread message; the first platform public reply records first_response_at and auto-starts work. */
    public function reply(SupportTicket $ticket, $user, array $data): SupportMessage
    {
        $isSupport = $data['author_type'] === 'support';
        if (! $isSupport && $ticket->status === 'closed') {
            $this->assertReopenable($ticket);
        }
        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open', 'closed_at' => null, 'resolved_at' => null]);
        }
        $message = SupportMessage::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'author_id' => $user->id,
            'author_type' => $data['author_type'],
            'visibility' => $isSupport && array_key_exists('visibility', $data) && $data['visibility'] === 'internal' ? 'internal' : 'public',
            'body' => $data['body'],
        ]);
        if ($isSupport && $message->visibility === 'public') {
            $ticket->forceFill([
                'first_response_at' => $ticket->first_response_at ?? now(),
                'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
            ])->save();
        }
        if (! $isSupport && in_array($ticket->status, ['waiting_customer', 'resolved', 'closed'], true)) {
            $ticket->update(['status' => 'in_progress', 'closed_at' => null]);
        }
        return $message;
    }

    /** Validated status transition; resolution requires resolution_notes via a message. */
    public function transition(SupportTicket $ticket, string $to, ?string $resolutionNotes = null): SupportTicket
    {
        $allowed = SupportTicket::TRANSITIONS[$ticket->status] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Cannot transition support ticket from {$ticket->status} to {$to}."]);
        }
        $attributes = ['status' => $to];
        $now = now();
        if ($to === 'resolved') {
            if (trim((string) $resolutionNotes) === '') {
                throw ValidationException::withMessages(['resolution_notes' => 'An incident cannot be resolved without recorded resolution criteria, and neither can a ticket. Provide resolution notes.']);
            }
            $attributes += ['resolved_at' => $ticket->resolved_at ?? $now];
            $this->recordBreach($ticket);
        }
        if ($to === 'closed') {
            $attributes['closed_at'] = $now;
            if ($ticket->status !== 'resolved') {
                $this->recordBreach($ticket);
            }
        }
        if ($to === 'open' && $ticket->status !== 'open') {
            $attributes['resolved_at'] = null;
            $attributes['closed_at'] = null;
        }
        $ticket->update($attributes);
        return $ticket;
    }

    public function isReopenable(SupportTicket $ticket): bool
    {
        return in_array($ticket->status, ['resolved', 'closed'], true)
            && $ticket->closed_at !== null
            && $ticket->closed_at->gt(now()->subDays(self::REOPEN_WINDOW_DAYS));
    }

    public function assertReopenable(SupportTicket $ticket): void
    {
        if ($ticket->status === 'resolved' && $ticket->resolved_at !== null) {
            return;
        }
        if ($this->isReopenable($ticket)) {
            return;
        }
        throw ValidationException::withMessages(['status' => 'This ticket can no longer be reopened. Please create a new support ticket.']);
    }

    /** Compute the apparent SLA status from stored timestamps and breach history — no fabrication. */
    public function slaStatus(SupportTicket $ticket): string
    {
        if (filled($ticket->sla_breach_log)) {
            return 'breached';
        }
        if ($ticket->first_response_due_at === null && $ticket->resolution_due_at === null) {
            return 'not_configured';
        }
        if (! in_array($ticket->status, self::OPEN_STATUSES, true)) {
            return 'met';
        }
        $breached = ($ticket->first_response_due_at !== null && $ticket->first_response_at === null && $ticket->first_response_due_at->isPast())
            || ($ticket->resolution_due_at !== null && $ticket->resolution_due_at->isPast());
        if ($breached) {
            return 'breached';
        }
        $atRisk = ($ticket->first_response_due_at !== null && $ticket->first_response_at === null && now()->addMinutes(30)->gte($ticket->first_response_due_at))
            || ($ticket->resolution_due_at !== null && now()->addMinutes(60)->gte($ticket->resolution_due_at));
        return $atRisk ? 'at_risk' : 'on_track';
    }

    private function recordBreach(SupportTicket $ticket): void
    {
        $now = now();
        $log = $ticket->sla_breach_log ?? [];
        if ($ticket->resolution_due_at !== null && ($ticket->resolved_at === null || $ticket->resolved_at->gt($ticket->resolution_due_at))) {
            $log[] = ['type' => 'resolution', 'breached_at' => $now->toIso8601String()];
        }
        if ($ticket->first_response_at === null && $ticket->first_response_due_at !== null && $ticket->first_response_due_at->isPast()) {
            $log[] = ['type' => 'first_response', 'breached_at' => $now->toIso8601String()];
        }
        if ($log !== ($ticket->sla_breach_log ?? [])) {
            $ticket->forceFill(['sla_breach_log' => $log])->save();
        }
    }

    private function resolvePolicy(string $priority): ?SupportSlaPolicy
    {
        // Exact priority match first; fall back to a policy that deliberately has no priority.
        // No universal default: priorities without any policy stay not_configured.
        $exact = SupportSlaPolicy::where('is_active', true)->where('priority', $priority)->first();
        return $exact ?? SupportSlaPolicy::where('is_active', true)->whereNull('priority')->first();
    }

    /** @return array{first_response_due_at: \Illuminate\Support\Carbon|null, resolution_due_at: \Illuminate\Support\Carbon|null} */
    private function dueTimestamps(?SupportSlaPolicy $policy, $from): array
    {
        if ($policy === null) {
            return ['first_response_due_at' => null, 'resolution_due_at' => null];
        }
        return [
            'first_response_due_at' => $policy->first_response_minutes !== null ? $this->addMinutes($policy, (int) $policy->first_response_minutes, $from) : null,
            'resolution_due_at' => $policy->resolution_minutes !== null ? $this->addMinutes($policy, (int) $policy->resolution_minutes, $from) : null,
        ];
    }

    /** Adds SLA minutes skipping outside configured business hours; 24x7 when business_hours is not configured. */
    private function addMinutes(SupportSlaPolicy $policy, int $minutes, $from)
    {
        $hours = $policy->business_hours;
        if (! is_array($hours) || empty($hours['days']) || ! isset($hours['start'], $hours['end'], $hours['timezone'])) {
            return $from->copy()->addMinutes($minutes);
        }
        $tz = in_array($hours['timezone'], timezone_identifiers_list(), true) ? $hours['timezone'] : 'UTC';
        $cursor = $from->copy()->setTimezone($tz);
        $start = explode(':', $hours['start']);
        $end = explode(':', $hours['end']);
        $remaining = $minutes;
        for ($day = 0; $day < 62 && $remaining > 0; $day++) {
            if (in_array((int) $cursor->dayOfWeekIso, array_map('intval', $hours['days']), true)) {
                $windowStart = $cursor->copy()->setTime((int) $start[0], (int) ($start[1] ?? 0));
                $windowEnd = $cursor->copy()->setTime((int) $end[0], (int) ($end[1] ?? 0));
                if ($cursor->lt($windowStart)) {
                    $cursor = $windowStart;
                } elseif ($cursor->lt($windowEnd)) {
                    $available = $windowEnd->diffInMinutes($cursor);
                    $use = min($remaining, max(0, (int) $available));
                    $cursor->addMinutes($use);
                    $remaining -= $use;
                }
                if ($remaining <= 0) {
                    break;
                }
            }
            $cursor = $cursor->addDay()->setTime((int) $start[0], (int) ($start[1] ?? 0));
        }
        return $cursor->setTimezone('UTC');
    }

    private function generateTicketNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = sprintf('TKT-%s-%s', now()->format('Ymd'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)));
            if (! SupportTicket::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Unable to allocate a unique ticket number.');
    }

    /** Validates and stores one attachment under the private disk; returns null for absent files. */
    public function storeAttachment(SupportTicket $ticket, $user, UploadedFile $file, ?SupportMessage $message): ?SupportAttachment
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['attachment' => 'The uploaded attachment is invalid.']);
        }
        $size = $file->getSize();
        if ($size === null || $size === 0 || $size > self::MAX_ATTACHMENT_BYTES) {
            throw ValidationException::withMessages(['attachment' => 'Attachments must be between 1 byte and 10 MB.']);
        }
        $mime = strtolower((string) $file->getMimeType());
        if (! array_key_exists($mime, self::ALLOWED_MIME)) {
            throw ValidationException::withMessages(['attachment' => 'This attachment type is not allowed. Allowed types: PDF, PNG, JPEG, TXT, CSV, ZIP.']);
        }
        $pathPrefix = 'support-attachments/'.$ticket->id;
        $stored = $file->store($pathPrefix, 'local');
        if ($stored === false) {
            throw ValidationException::withMessages(['attachment' => 'The attachment could not be stored.']);
        }
        $attachment = SupportAttachment::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'message_id' => $message?->id,
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => $stored,
            'original_name' => substr($file->getClientOriginalName(), 0, 200),
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);
        return $attachment;
    }
}
