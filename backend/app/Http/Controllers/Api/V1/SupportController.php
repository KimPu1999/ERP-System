<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{SupportAttachment, SupportMessage, SupportSlaPolicy, SupportTicket};
use App\Services\{AuditLogger, SensitiveDataRedactor, SupportTicketService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;

/**
 * Customer support tickets (business side) and authorized platform-side management.
 * Tenant isolation: resolved by the `tenant` middleware + RLS for business routes,
 * and every platform read goes through explicit tenant scoping by filters.
 */
final class SupportController
{
    public function __construct(private readonly SupportTicketService $tickets, private readonly AuditLogger $audit, private readonly SensitiveDataRedactor $redactor)
    {
    }

    // ------------------------------------------------------------------ business

    public function tickets(Request $request): JsonResponse
    {
        $query = SupportTicket::where('tenant_id', app(TenantContext::class)->id())
            ->with(['requester:id,name,email', 'assignee:id,name'])
            ->orderByDesc('created_at');
        if ($status = $request->query('status')) {
            $query->whereIn('status', explode(',', (string) $status));
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('subject', 'ilike', "%{$search}%")->orWhere('ticket_number', 'ilike', "%{$search}%"));
        }
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        $page->getCollection()->transform(fn (SupportTicket $ticket) => $this->serialize($ticket));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function createTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'in:'.implode(',', SupportTicket::PRIORITIES)],
            'category' => ['nullable', 'in:'.implode(',', SupportTicket::CATEGORIES)],
        ]);
        $ticket = $this->tickets->createTicket(app(TenantContext::class)->id(), $request->user(), $data);
        $this->handleUploads($request, $this->tickets, $ticket, $request->user());
        $this->audit->record($request, 'support.ticket.created', $ticket->tenant_id, $ticket, ['ticket_number' => $ticket->ticket_number]);
        return response()->json(['data' => $this->serialize($ticket->fresh(['requester:id,name,email', 'assignee:id,name']))], 201);
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $ticket = $this->authorizeBusinessTicket($ticket);
        $this->audit->record($request, 'support.ticket.viewed', $ticket->tenant_id, $ticket);
        return response()->json(['data' => $this->serialize($ticket, thread: true)]);
    }

    /** Requester reply; customer replies reopen resolved/closed tickets inside the documented window. */
    public function reply(Request $request, string $ticket): JsonResponse
    {
        $ticket = $this->authorizeBusinessTicket($ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $message = $this->tickets->reply($ticket, $request->user(), $data + ['author_type' => 'customer']);
        $this->handleUploads($request, $this->tickets, $ticket, $request->user(), $message);
        $this->audit->record($request, 'support.ticket.replied', $ticket->tenant_id, $ticket, ['message_id' => $message->id]);
        return response()->json(['data' => $this->serializeMessage($message, requester: true)], 201);
    }

    public function downloadAttachment(Request $request, string $ticket, SupportAttachment $attachment): StreamedResponse
    {
        abort_if($attachment->ticket_id !== $ticket, 404);
        $this->authorizeBusinessTicket($ticket);
        return $this->streamAttachment($attachment);
    }

    public function closeTicket(Request $request, string $ticket): JsonResponse
    {
        $ticket = $this->authorizeBusinessTicket($ticket);
        if ($ticket->status === 'resolved') {
            $this->tickets->transition($ticket, 'closed');
        } elseif (in_array($ticket->status, ['open', 'waiting_customer'], true)) {
            if ($ticket->resolved_at === null) {
                $this->tickets->transition($ticket, 'resolved', 'Closed by the requester.');
                $ticket = $ticket->refresh();
            }
            $this->tickets->transition($ticket, 'closed');
        } else {
            abort(422, 'This ticket cannot be closed in its current state.');
        }
        $this->audit->record($request, 'support.ticket.closed_by_requester', $ticket->tenant_id, $ticket);
        return response()->json(['data' => $this->serialize($ticket->refresh())]);
    }

    public function reopenTicket(Request $request, string $ticket): JsonResponse
    {
        $ticket = $this->authorizeBusinessTicket($ticket);
        $this->tickets->assertReopenable($ticket);
        $this->tickets->transition($ticket, 'open');
        $this->audit->record($request, 'support.ticket.reopened', $ticket->tenant_id, $ticket);
        return response()->json(['data' => $this->serialize($ticket->refresh())]);
    }

    // ------------------------------------------------------------------ platform

    public function platformList(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with(['tenant:id,name', 'requester:id,name,email', 'assignee:id,name']);
        foreach (['status' => 'status', 'priority' => 'priority', 'category' => 'category', 'tenant_id' => 'tenant_id'] as $filter => $column) {
            $value = $request->query($filter);
            if ($value) {
                $column === 'status'
                    ? $query->whereIn($column, explode(',', (string) $value))
                    : $query->where($column, $value);
            }
        }
        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('subject', 'ilike', "%{$search}%")->orWhere('ticket_number', 'ilike', "%{$search}%"));
        }
        if ($posture = $request->query('sla_status')) {
            if ($posture === 'breached') {
                $query->where(fn ($q) => $q->whereNotNull('sla_breach_log')->orWhere(fn ($b) => $b->where('first_response_at', null)->where('first_response_due_at', '<', now()))->orWhere(fn ($b) => $b->where('resolved_at', null)->where('resolution_due_at', '<', now())));
            } elseif ($posture === 'open_sla') {
                $query->whereIn('status', SupportTicketService::OPEN_STATUSES);
            } elseif ($posture === 'not_configured') {
                $query->whereNull('first_response_due_at')->whereNull('resolution_due_at')->whereNull('sla_breach_log');
            }
        }
        $page = $query->orderByDesc('created_at')->paginate(min((int) ($request->query('per_page', 25)), 100));
        $page->getCollection()->transform(fn (SupportTicket $ticket) => $this->serialize($ticket));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function platformShow(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket->load(['tenant:id,name', 'requester:id,name,email', 'assignee:id,name']);
        return response()->json(['data' => $this->serialize($ticket, thread: true, includeInternal: true)]);
    }

    public function platformUpdate(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'priority' => ['sometimes', 'in:'.implode(',', SupportTicket::PRIORITIES)],
            'category' => ['sometimes', 'in:'.implode(',', SupportTicket::CATEGORIES)],
            'assigned_to' => ['sometimes', 'nullable', 'uuid', 'exists:users,id'],
            'status' => ['sometimes', 'in:'.implode(',', SupportTicket::STATUSES)],
        ]);
        $before = $ticket->only(array_keys($data));
        foreach (['priority', 'category', 'assigned_to'] as $field) {
            if (array_key_exists($field, $data)) {
                $ticket->update([$field => $data[$field]]);
            }
        }
        if (isset($data['status'])) {
            $this->tickets->transition($ticket, $data['status'], $data['status'] === 'resolved' ? (string) $request->input('resolution_notes') : null);
        }
        $this->audit->record($request, 'platform.support.ticket.updated', $ticket->tenant_id, $ticket, ['changes' => $this->redactor->redact(['before' => $before, 'after' => $data])]);
        return response()->json(['data' => $this->serialize($ticket->refresh(['tenant:id,name', 'requester:id,name,email', 'assignee:id,name']))]);
    }

    /** Support reply (public) or internal note (never returned to the business conversation). */
    public function platformReply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', 'in:public,internal'],
        ]);
        $message = $this->tickets->reply($ticket, $request->user(), $data + ['author_type' => 'support']);
        $this->audit->record($request, $message->visibility === 'internal' ? 'platform.support.note_added' : 'platform.support.replied', $ticket->tenant_id, $ticket, ['message_id' => $message->id]);
        return response()->json(['data' => $this->serializeMessage($message, requester: false)], 201);
    }

    public function platformDownloadAttachment(Request $request, SupportTicket $ticket, SupportAttachment $attachment): StreamedResponse
    {
        abort_if($attachment->ticket_id !== $ticket->id, 404);
        $this->audit->record($request, 'platform.support.attachment_downloaded', $ticket->tenant_id, $ticket, ['attachment_id' => $attachment->id]);
        return $this->streamAttachment($attachment);
    }

    public function slaPolicies(): JsonResponse
    {
        return response()->json(['data' => SupportSlaPolicy::orderBy('priority')->get()]);
    }

    public function storeSlaPolicy(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['nullable', 'in:'.implode(',', SupportTicket::PRIORITIES)],
            'first_response_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.days' => ['nullable', 'array', 'max:7'],
            'business_hours.start' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'business_hours.end' => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'business_hours.timezone' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
        ]);
        $policy = SupportSlaPolicy::create($data);
        $audit->record($request, 'platform.support.sla_policy_created', null, $policy, ['name' => $policy->name]);
        return response()->json(['data' => $policy], 201);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'support-tickets-'.now()->format('Ymd-His').'.csv';
        $this->audit->record($request, 'platform.support.tickets_exported', $request->query('tenant_id'), null, ['filters' => array_intersect_key($request->query(), ['status' => 1, 'priority' => 1, 'tenant_id' => 1])]);
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ticket_number', 'tenant', 'subject', 'category', 'priority', 'status', 'assigned_to', 'sla_status', 'created_at', 'first_response_at', 'resolved_at', 'closed_at']);
            $this->platformSlaBuilder($request)->with(['tenant:id,name', 'assignee:id,name'])->orderBy('created_at')->chunk(500, function ($chunk) use ($out): void {
                foreach ($chunk as $ticket) {
                    fputcsv($out, [$ticket->ticket_number, $ticket->tenant?->name, $ticket->subject, $ticket->category, $ticket->priority, $ticket->status, $ticket->assignee?->name, $this->tickets->slaStatus($ticket), optional($ticket->created_at)->toIso8601String(), optional($ticket->first_response_at)->toIso8601String(), optional($ticket->resolved_at)->toIso8601String(), optional($ticket->closed_at)->toIso8601String()]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function platformSlaBuilder(Request $request)
    {
        $builder = SupportTicket::query();
        foreach (['status', 'priority', 'category', 'tenant_id'] as $filter) {
            $value = $request->query($filter);
            $value && $builder->where($filter, $value);
        }
        return $builder;
    }

    // ------------------------------------------------------------------ internals

    private function authorizeBusinessTicket(string $ticketId): SupportTicket
    {
        $ticket = SupportTicket::whereKey($ticketId)->firstOrFail();
        abort_unless($ticket->tenant_id === app(TenantContext::class)->id(), 404);
        return $ticket;
    }

    /** Sanitized ticket payload for both audiences. Internal visibility is platform-only. */
    private function serialize(SupportTicket $ticket, bool $thread = false, bool $includeInternal = false): array
    {
        $service = $this->tickets;
        $payload = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'category' => $ticket->category,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'sla_status' => $service->slaStatus($ticket),
            'first_response_due_at' => optional($ticket->first_response_due_at)->toIso8601String(),
            'resolution_due_at' => optional($ticket->resolution_due_at)->toIso8601String(),
            'first_response_at' => optional($ticket->first_response_at)->toIso8601String(),
            'resolved_at' => optional($ticket->resolved_at)->toIso8601String(),
            'closed_at' => optional($ticket->closed_at)->toIso8601String(),
            'created_at' => optional($ticket->created_at)->toIso8601String(),
            'updated_at' => optional($ticket->updated_at)->toIso8601String(),
            'requester' => $ticket->requester ? ['id' => $ticket->requester->id, 'name' => $ticket->requester->name, 'email' => $ticket->requester->email] : null,
            'assignee' => $ticket->assignee ? ['id' => $ticket->assignee->id, 'name' => $ticket->assignee->name] : null,
            'reopenable' => $service->isReopenable($ticket),
        ];
        if (isset($ticket->tenant->name)) {
            $payload['tenant'] = ['id' => $ticket->tenant->id, 'name' => $ticket->tenant->name];
        }
        if ($thread) {
            $messages = $ticket->messages()->with(['author:id,name,email', 'uploads'])->orderBy('created_at')->get();
            if (! $includeInternal) {
                $messages = $messages->where('visibility', 'public')->values();
            }
            $payload['thread'] = $messages->map(fn ($message) => $this->serializeMessage($message));
        }
        return $payload;
    }

    private function serializeMessage(SupportMessage $message, bool $requester = false): array
    {
        return [
            'id' => $message->id,
            'author_type' => $message->author_type,
            'visibility' => $message->visibility,
            'author' => $message->author?->only(['id', 'name', 'email']),
            'body' => $message->body,
            'attachments' => $message->uploads->map(fn ($a) => [
                'id' => $a->id,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => $a->size_bytes,
                'download_url' => "/support/tickets/{$a->ticket_id}/attachments/{$a->id}",
            ])->values()->all(),
            'created_at' => optional($message->created_at)->toIso8601String(),
            'me' => false,
            'requester_scope' => $requester,
        ];
    }

    private function handleUploads(Request $request, SupportTicketService $service, SupportTicket $ticket, User $user, ?SupportMessage $message = null): void
    {
        $files = array_filter($request->file('attachments', []), fn ($f) => $f instanceof UploadedFile);
        foreach ($files as $file) {
            $service->storeAttachment($ticket, $user, $file, $message);
        }
    }

    private function streamAttachment(SupportAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->disk === 'local' && Storage::disk('local')->exists($attachment->path), 404);
        $fallback = preg_match('/^[a-z0-9.]+$/i', $attachment->original_name) ? (string) $attachment->original_name : 'attachment';
        return response()->streamDownload(function () use ($attachment): void {
            $stream = Storage::disk('local')->readStream($attachment->path);
            fpassthru($stream);
        }, $fallback, ['Content-Type' => $attachment->mime_type]);
    }
}
