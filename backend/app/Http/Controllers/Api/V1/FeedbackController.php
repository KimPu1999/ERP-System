<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{FeedbackNote, ProductFeedback, ReleaseRecord, RoadmapItem, SatisfactionResponse};
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Storage;

/**
 * Phase 16 business-side product feedback, published release notes and the
 * customer-visible (approved) roadmap. Tenant isolation is enforced by the
 * `tenant` middleware, explicit tenant scoping in every query and PostgreSQL
 * RLS policies on product_feedback / feedback_notes / satisfaction_responses.
 */
final class FeedbackController
{
    private const ALLOWED_ATTACHMENT_MIMES = [
        'image/png', 'image/jpeg', 'application/pdf', 'text/plain', 'text/csv',
        'application/zip', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ProductFeedback::where('tenant_id', app(TenantContext::class)->id())
            ->with('assignee:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('subject', 'ilike', "%{$s}%")->orWhere('feedback_number', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at');
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        $page->getCollection()->transform(fn (ProductFeedback $f) => $this->serializeOwn($f, brief: true));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', ProductFeedback::CATEGORIES)],
            'module' => ['nullable', 'string', 'max:80'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:10000'],
        ]);
        $attachment = $request->file('attachment');
        if ($attachment !== null) {
            $this->validateAttachment($attachment);
        }

        $payload = [
            'tenant_id' => app(TenantContext::class)->id(),
            'feedback_number' => $this->nextNumber(),
            'category' => $data['category'],
            'module' => $data['module'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'submitted',
            'submitted_by' => $request->user()->id,
            'source' => 'web_form',
            // Duplicate detection: a stable normalized fingerprint per tenant — not a hard block,
            // duplicates are surfaced for safe reviewer handling on the platform side.
            'content_hash' => hash('sha256', implode('|', [
                app(TenantContext::class)->id(), $data['category'], strtolower(trim($data['subject'])),
            ])),
        ];
        if ($attachment !== null) {
            $path = $attachment->store('feedback-attachments', 'local');
            $payload['attachments'] = [[
                'disk' => 'local', 'path' => $path, 'original_name' => $attachment->getClientOriginalName(),
                'mime_type' => $attachment->getClientMimeType(), 'size_bytes' => $attachment->getSize(),
                'uploaded_by' => $request->user()->id, 'uploaded_at' => now()->toIso8601String(),
            ]];
        }

        $feedback = ProductFeedback::create($payload);
        $this->audit->record($request, 'business.feedback.submitted', $feedback->tenant_id, $feedback, ['category' => $feedback->category, 'module' => $feedback->module]);
        return response()->json(['data' => $this->serializeOwn($feedback)], 201);
    }

    public function show(Request $request, string $feedback): JsonResponse
    {
        $model = ProductFeedback::where('id', $feedback)->with('assignee:id,name')->first();
        abort_unless($model !== null && $model->tenant_id === app(TenantContext::class)->id(), 404);
        // Business users only ever see the public thread; internal platform notes stay private.
        $notes = FeedbackNote::where('feedback_id', $model->id)->where('visibility', 'public')->with('author:id,name')->orderBy('created_at')->get()
            ->map(fn (FeedbackNote $n) => ['id' => $n->id, 'author_type' => $n->author_type, 'body' => $n->body, 'author' => $n->author?->only(['id', 'name']), 'created_at' => $n->created_at?->toIso8601String()])->all();
        $history = \App\Models\FeedbackStatusHistory::where('feedback_id', $model->id)->orderBy('created_at')->get()
            ->map(fn ($h) => ['from_status' => $h->from_status, 'to_status' => $h->to_status, 'created_at' => $h->created_at?->toIso8601String()])->all();
        return response()->json(['data' => $this->serializeOwn($model) + [
            'notes' => $notes, 'status_history' => $history,
            'has_satisfaction_response' => SatisfactionResponse::where('user_id', $request->user()->id)->where('context', 'product_feedback')->where('context_id', $model->id)->exists(),
        ]]);
    }

    public function comment(Request $request, string $feedback): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $model = ProductFeedback::where('id', $feedback)->first();
        abort_unless($model !== null && $model->tenant_id === app(TenantContext::class)->id(), 404);
        if ($model->status === 'closed' && ! in_array('needs_information', ProductFeedback::TRANSITIONS[$model->status] ?? [], true)) {
            return response()->json(['message' => 'This feedback is closed. Submit new feedback if the problem recurs.'], 422);
        }
        $note = FeedbackNote::create([
            'tenant_id' => $model->tenant_id, 'feedback_id' => $model->id, 'author_id' => $request->user()->id,
            'author_type' => 'business', 'visibility' => 'public', 'body' => $data['body'], 'created_at' => now(),
        ]);
        $this->audit->record($request, 'business.feedback.commented', $model->tenant_id, $note);
        return response()->json(['data' => ['id' => $note->id, 'body' => $note->body, 'created_at' => $note->created_at?->toIso8601String()]], 201);
    }

    public function satisfaction(Request $request, string $feedback): JsonResponse
    {
        $data = $request->validate(['score' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $model = ProductFeedback::where('id', $feedback)->first();
        abort_unless($model !== null && $model->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless(in_array($model->status, ['resolved', 'closed'], true), 422, 'Satisfaction feedback is collected only after the item is resolved.');

        $existing = SatisfactionResponse::where('user_id', $request->user()->id)->where('context', 'product_feedback')->where('context_id', $model->id)->first();
        abort_unless($existing === null, 422, 'You already provided satisfaction feedback for this item.');

        $response = SatisfactionResponse::create([
            'tenant_id' => $model->tenant_id, 'user_id' => $request->user()->id, 'context' => 'product_feedback',
            'context_id' => $model->id, 'score' => $data['score'], 'comment' => $data['comment'] ?? null, 'submitted_at' => now(),
        ]);
        $model->update(['satisfaction_score' => $data['score'], 'satisfaction_comment' => $data['comment'] ?? null]);
        $this->audit->record($request, 'business.feedback.satisfaction_recorded', $model->tenant_id, $response, ['score' => $data['score']]);
        return response()->json(['data' => ['id' => $response->id, 'score' => $response->score]], 201);
    }

    /** Customer-facing release notes: only published, customer-visible records, business or both audiences. */
    public function releases(Request $request): JsonResponse
    {
        $records = ReleaseRecord::where('customer_visible', true)->whereIn('audience', ['business', 'both'])
            ->where('status', 'deployed')
            ->whereNotNull('published_at')
            ->orderByDesc('deployed_at')->limit(20)->get(['id', 'version', 'release_notes', 'release_notes_my', 'release_notes_sections', 'known_issues', 'deployed_at', 'published_at']);
        return response()->json(['data' => $records->map(fn (ReleaseRecord $r) => [
            'id' => $r->id, 'version' => $r->version, 'release_notes' => $r->release_notes,
            'release_notes_my' => $r->release_notes_my, 'sections' => $r->release_notes_sections,
            'known_limitations' => $r->known_issues, 'deployed_at' => $r->deployed_at?->toIso8601String(), 'published_at' => $r->published_at?->toIso8601String(),
        ])->all(), 'meta' => ['available' => $records->isNotEmpty()]]);
    }

    /** Approved public roadmap: internal security work is never published (visibility column is the gate). */
    public function roadmap(Request $request): JsonResponse
    {
        $items = RoadmapItem::where('visibility', 'customer_visible')
            ->whereIn('status', ['planned', 'in_progress', 'in_testing', 'ready_for_release', 'released'])
            ->orderBy('priority')->orderBy('status')->limit(200)
            ->get(['id', 'title', 'customer_summary', 'module', 'status', 'target_quarter']);
        return response()->json(['data' => $items->map(fn (RoadmapItem $i) => [
            'id' => $i->id, 'title' => $i->title, 'summary' => $i->customer_summary, 'module' => $i->module,
            'status' => $i->status, 'target_quarter' => filled($i->target_quarter) ? $i->target_quarter : null,
        ])->all()]);
    }

    // ------------------------------------------------------------ internals

    private function nextNumber(): string
    {
        for ($i = 0; $i < 6; $i++) {
            $candidate = sprintf('FB-%s-%s', now()->format('ymd'), mb_strtoupper((string) random_int(100000, 999999)));
            if (ProductFeedback::where('feedback_number', $candidate)->doesntExist()) {
                return $candidate;
            }
        }
        return sprintf('FB-%s-%s', now()->format('ymdHis'), mb_strtoupper((string) random_int(100, 999)));
    }

    private function validateAttachment(UploadedFile $attachment): void
    {
        abort_if(! $attachment->isValid(), 422, 'The attachment upload failed. Please retry.');
        abort_if($attachment->getSize() > 5 * 1024 * 1024, 422, 'Attachments are limited to 5 MB.');
        abort_if(! in_array(mb_strtolower((string) $attachment->getClientMimeType()), self::ALLOWED_ATTACHMENT_MIMES, true), 422, 'Attachment type is not allowed. Permitted: PNG, JPEG, PDF, TXT, CSV, ZIP, DOC/DOCX, XLSX.');
        $original = $attachment->getClientOriginalName();
        abort_if($original !== null && preg_match('/\.php\d*(\.|$)/i', $original) === 1, 422, 'Executable attachments are not allowed.');
    }

    private function serializeOwn(ProductFeedback $f, bool $brief = false): array
    {
        $data = [
            'id' => $f->id, 'feedback_number' => $f->feedback_number, 'category' => $f->category,
            'module' => $f->module, 'subject' => $f->subject, 'status' => $f->status,
            'has_attachments' => filled($f->attachments), 'assignee' => $f->assignee?->only(['id', 'name']),
            'created_at' => $f->created_at?->toIso8601String(),
        ];
        if (! $brief) {
            $data += ['message' => $f->message, 'attachments' => $f->attachments, 'satisfaction_score' => $f->satisfaction_score,
                'resolution_outcome' => $f->resolution_outcome];
        }
        return $data;
    }
}
