<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{
    DefectEvent, DefectRecord, FeedbackNote, FeatureFlag, FeatureRequest, FeatureRequestEvent, FeatureRollout,
    FeatureRolloutEvent, ImprovementReview, MaturityAssessment, MonitoringIncident, MonitoringObservation,
    ProductFeedback, FeedbackStatusHistory, ReleaseRecord, RoadmapItem, SatisfactionResponse, ServiceLevelObjective,
    TenantFeatureFlag,
};
use App\Services\{AuditLogger, MonitoringService};
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 16 Improvement Center (platform side).
 *
 * Sources of truth are NOT duplicated: support tickets (Phase 12), monitoring
 * incidents/observations (Phase 7), security center (Phase 8), growth analytics
 * (Phase 13) and release_records (Phase 12) are linked or read directly.
 * Unknown data renders as explicit `unknown` / `not_configured` markers —
 * nothing is invented (ratings, uptime, adoption, effort estimates).
 */
final class PlatformImprovementController
{
    private const FEEDBACK_AFTER_RESOLVE = ['resolved', 'closed'];

    public function __construct(private readonly AuditLogger $audit, private readonly MonitoringService $monitoring)
    {
    }

    // ------------------------------------------------------------- overview

    public function overview(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.overview_viewed', null);

        $feedbackByStatus = ProductFeedback::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $requestsByStatus = FeatureRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $defectsBySeverity = DefectRecord::whereIn('status', array_diff(DefectRecord::STATUSES, ['closed']))
            ->selectRaw('severity, count(*) as total')->groupBy('severity')->pluck('total', 'severity');
        $currentRelease = ReleaseRecord::where('status', 'deployed')->orderByDesc('deployed_at')->first();

        return response()->json(['data' => [
            'last_updated_at' => now()->toIso8601String(),
            'feedback' => [
                'open' => ProductFeedback::whereIn('status', ['submitted', 'under_review', 'needs_information', 'accepted', 'planned'])->count(),
                'by_status' => $this->intMap($feedbackByStatus),
                'feature_requests_submitted' => ProductFeedback::where('category', 'feature_request')->count(),
            ],
            'feature_requests' => [
                'open' => FeatureRequest::whereIn('status', ['new', 'triaged', 'planned', 'in_development', 'in_testing'])->count(),
                'by_status' => $this->intMap($requestsByStatus),
                'approved' => FeatureRequest::where('status', 'planned')->count(),
            ],
            'defects' => [
                'open_total' => (int) array_sum($this->intMap($defectsBySeverity)),
                'critical_open' => (int) ($this->intMap($defectsBySeverity)['P0'] ?? 0),
                'high_open' => (int) ($this->intMap($defectsBySeverity)['P1'] ?? 0),
                'by_severity' => $this->intMap($defectsBySeverity),
            ],
            'planned_releases' => ReleaseRecord::whereIn('status', ['draft', 'ready_for_qa', 'qa_passed', 'approved', 'deploying'])->count(),
            'current_release' => $currentRelease ? [
                'version' => $currentRelease->version, 'environment' => $currentRelease->environment,
                'deployed_at' => $currentRelease->deployed_at?->toIso8601String(),
            ] : null,
            'adoption_summary' => [
                'source' => 'phase_13_growth_analytics',
                'endpoint' => '/api/v1/platform/growth/adoption',
                'not_aggregated_here' => 'Module adoption is owned by Phase 13; page views alone do not establish adoption.',
            ],
            'customer_satisfaction' => $this->satisfactionSummary(null),
            'reliability' => [
                'source' => 'phase 7 monitoring',
                'services' => collect($this->monitoring->overview()['services'] ?? [])
                    ->map(fn ($s) => ['service' => $s['service'], 'status' => $s['status'] ?? 'unknown'])->values()->all(),
            ],
            'engineering_quality' => $this->qualityTrend(),
            'status_note' => 'Every count derives from real records; absent measurement data is omitted rather than estimated.',
        ]]);
    }

    // ------------------------------------------------------------- feedback

    public function feedbackIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.feedback_listed', null);
        $query = ProductFeedback::query()->with(['tenant:id,name', 'assignee:id,name', 'submitter:id,name,email'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('subject', 'ilike', "%{$s}%")->orWhere('feedback_number', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at');
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        $page->getCollection()->transform(fn (ProductFeedback $f) => $this->serializeFeedback($f, brief: true));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function feedbackShow(Request $request, ProductFeedback $feedback): JsonResponse
    {
        $duplicates = ProductFeedback::where('content_hash', $feedback->content_hash)
            ->where('id', '!=', $feedback->id)
            ->limit(10)->get(['id', 'feedback_number', 'tenant_id', 'subject', 'status', 'created_at']);

        return response()->json(['data' => $this->serializeFeedback($feedback->load(['tenant:id,name', 'assignee:id,name', 'submitter:id,name,email', 'duplicateOf:id,feedback_number,subject'])) + [
            'notes' => $this->feedbackNotes($feedback, internal: true),
            'history' => $this->history($feedback->id),
            'possible_transitions' => ProductFeedback::TRANSITIONS[$feedback->status] ?? [],
            'duplicates' => $duplicates->map(fn ($d) => ['id' => $d->id, 'feedback_number' => $d->feedback_number, 'subject' => $d->subject, 'status' => $d->status, 'created_at' => $d->created_at?->toIso8601String()])->all(),
            'linked_ticket' => $feedback->support_ticket_id ? ['id' => $feedback->support_ticket_id, 'endpoint' => '/api/v1/platform/support/tickets/{id}'] : null,
        ] + ['ticket_summary' => $this->ticketSummary($feedback->support_ticket_id)]]);
    }

    public function feedbackUpdate(Request $request, ProductFeedback $feedback): JsonResponse
    {
        $allowed = ['submitted', 'under_review', 'needs_information', 'accepted', 'planned', 'declined', 'resolved', 'closed'];
        if ($request->filled('status')) {
            return $this->feedbackTransition($request, $feedback);
        }
        $data = $request->validate([
            'category' => ['in:'.implode(',', ProductFeedback::CATEGORIES)],
            'module' => ['nullable', 'string', 'max:80'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'duplicate_of_id' => ['nullable', 'uuid', 'exists:product_feedback,id'],
            'support_ticket_id' => ['nullable', 'uuid', 'exists:support_tickets,id'],
            'resolution_outcome' => ['nullable', 'string', 'max:2000'],
        ]);
        unset($allowed);
        if (isset($data['duplicate_of_id']) && $data['duplicate_of_id'] === $feedback->id) {
            return response()->json(['message' => 'A feedback entry cannot be marked as its own duplicate.'], 422);
        }
        $previousStatus = $feedback->status;
        $feedback->update($data);
        if (isset($data['duplicate_of_id']) && $feedback->status === 'submitted') {
            // Marking duplicates is an audit-recorded workflow action, not arbitrary status editing.
            $feedback->update(['status' => 'closed']);
            $this->applyStatus($feedback, $previousStatus, 'closed', $request, 'Marked as duplicate of FB record.');
        }
        if (isset($data['assigned_to']) && $feedback->status === 'submitted' && !isset($data['duplicate_of_id'])) {
            $this->applyStatus($feedback, $previousStatus, 'under_review', $request, 'Reviewer assigned.');
        }
        if (isset($data['resolution_outcome']) && in_array($feedback->status, self::FEEDBACK_AFTER_RESOLVE, true)) {
            $feedback->update(['resolution_recorded_by' => $request->user()->id]);
        }
        $this->audit->record($request, 'platform.improvement.feedback_updated', $feedback->tenant_id, $feedback, ['fields' => array_keys($data)]);
        return response()->json(['data' => $this->serializeFeedback($feedback->fresh(['tenant:id,name', 'assignee:id,name']))]);
    }

    public function feedbackTransition(Request $request, ProductFeedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', ProductFeedback::STATUSES)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'resolution_outcome' => ['nullable', 'string', 'max:2000'],
        ]);
        $to = $data['status'];
        abort_unless(in_array($to, ProductFeedback::TRANSITIONS[$feedback->status] ?? [], true), 422, "Status transition from {$feedback->status} to {$to} is not allowed.");
        if ($to === 'resolved' && blank(($data['resolution_outcome'] ?? null) ?? $feedback->resolution_outcome)) {
            return response()->json(['message' => 'Resolving feedback requires a recorded resolution outcome.'], 422);
        }
        $previousStatus = $feedback->status;
        $attributes = ['status' => $to];
        if ($to === 'resolved') {
            $attributes += ['resolution_outcome' => $data['resolution_outcome'] ?? $feedback->resolution_outcome, 'resolution_recorded_by' => $request->user()->id];
        }
        $feedback->update($attributes);
        $this->applyStatus($feedback, $previousStatus, $to, $request, $data['reason'] ?? null);
        $this->audit->record($request, 'platform.improvement.feedback_status_changed', $feedback->tenant_id, $feedback, ['from' => $previousStatus, 'to' => $to]);
        return response()->json(['data' => $this->serializeFeedback($feedback->fresh(['tenant:id,name', 'assignee:id,name']))]);
    }

    public function feedbackConvert(Request $request, ProductFeedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:feature_request,defect'],
            'title' => ['required', 'string', 'max:180'],
            'module' => ['nullable', 'string', 'max:80'],
        ]);
        abort_unless(in_array($feedback->status, ['submitted', 'under_review', 'needs_information', 'accepted'], true), 422, "Feedback in status {$feedback->status} is not eligible for conversion.");

        $created = null;
        if ($data['type'] === 'feature_request') {
            $created = FeatureRequest::create([
                'request_key' => $this->nextKey('FR', FeatureRequest::class, 'request_key'),
                'title' => $data['title'], 'description' => "Converted from customer feedback {$feedback->feedback_number}:\n{$feedback->message}",
                'module' => $data['module'] ?? $feedback->module,
                'origin' => [['type' => 'feedback', 'id' => $feedback->id, 'number' => $feedback->feedback_number]],
                'requester_reference' => $feedback->submitter?->email, 'status' => 'new',
            ]);
        } else {
            $created = DefectRecord::create([
                'defect_number' => $this->nextKey('DEF', DefectRecord::class, 'defect_number'),
                'title' => $data['title'], 'module' => $data['module'] ?? $feedback->module,
                'environment' => 'staging', 'severity' => 'P2', 'status' => 'open',
                'source_feedback_id' => $feedback->id,
            ]);
        }
        DB::table('feature_request_events')->insert(['id' => Str::uuid(), 'request_id' => $created->id ?? null, 'event' => 'created_from_feedback', 'payload' => json_encode(['feedback_id' => $feedback->id]), 'actor_id' => $request->user()->id, 'created_at' => now()]);

        $previousStatus = $feedback->status;
        $target = in_array('planned', ProductFeedback::TRANSITIONS[$feedback->status] ?? [], true) ? 'planned' : null;
        abort_if($target === null, 422, 'Feedback must be under review or accepted before it can be converted.');
        $feedback->update(['status' => $target]);
        $createdRef = $created->request_key ?? $created->defect_number;
        $this->applyStatus($feedback, $previousStatus, $target, $request, "Converted to {$data['type']} {$createdRef}.");

        $this->audit->record($request, 'platform.improvement.feedback_converted', $feedback->tenant_id, $created, ['type' => $data['type'], 'feedback' => $feedback->feedback_number, 'created_ref' => $createdRef]);
        return response()->json(['data' => ['record' => ['id' => $created->id, 'number' => $createdRef, 'type' => $data['type']], 'feedback' => $this->serializeFeedback($feedback)]], 201);
    }

    public function feedbackNote(Request $request, ProductFeedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'visibility' => ['required', 'in:public,internal'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $note = FeedbackNote::create([
            'tenant_id' => $feedback->tenant_id, 'feedback_id' => $feedback->id, 'author_id' => $request->user()->id,
            'author_type' => 'platform', 'visibility' => $data['visibility'], 'body' => $data['body'], 'created_at' => now(),
        ]);
        $this->audit->record($request, 'platform.improvement.feedback_note_added', $feedback->tenant_id, $note, ['visibility' => $data['visibility']]);
        return response()->json(['data' => ['id' => $note->id, 'visibility' => $note->visibility, 'body' => $note->body, 'author' => ['id' => $request->user()->id, 'name' => $request->user()->name]]], 201);
    }

    // ---------------------------------------------------- feature requests

    public function requestsIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.requests_listed', null);
        $query = FeatureRequest::query()->with('owner:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('owner'), fn ($q, $o) => $q->where('owner_id', $o))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('request_key', 'ilike', "%{$s}%")))
            ->orderByDesc('created_at');
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        $page->getCollection()->transform(fn (FeatureRequest $r) => $this->serializeRequest($r, brief: true));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function requestShow(Request $request, FeatureRequest $requestModel): JsonResponse
    {
        $events = FeatureRequestEvent::where('request_id', $requestModel->id)->orderByDesc('created_at')->limit(50)->get();
        $feedback = ProductFeedback::whereIn('id', collect($requestModel->origin ?? [])->where('type', 'feedback')->pluck('id'))->get(['id', 'feedback_number', 'subject', 'status']);
        return response()->json(['data' => $this->serializeRequest($requestModel->load('owner:id,name')) + [
            'events' => $events->map(fn ($e) => ['event' => $e->event, 'payload' => $e->payload, 'actor_id' => $e->actor_id, 'created_at' => $e->created_at?->toIso8601String()])->all(),
            'possible_transitions' => FeatureRequest::TRANSITIONS[$requestModel->status] ?? [],
            'source_feedback' => $feedback->map(fn ($f) => ['id' => $f->id, 'number' => $f->feedback_number, 'subject' => $f->subject, 'status' => $f->status])->all(),
        ]]);
    }

    public function requestStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:10000'],
            'module' => ['nullable', 'string', 'max:80'],
            'business_impact' => ['nullable', 'string', 'max:4000'],
            'requester_reference' => ['nullable', 'string', 'max:120'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'effort_estimate' => ['required', 'string', 'max:40'], // explicit at entry; null renders "Not Estiminated" only on legacy rows
            'effort_estimated' => ['required', 'boolean'],
            'risk' => ['nullable', 'in:low,medium,high'],
            'origin' => ['nullable', 'array'],
        ]);
        $estimated = $data['effort_estimated'];
        unset($data['effort_estimated']);
        $data['effort_estimate'] = $estimated ? $data['effort_estimate'] : null;
        $model = FeatureRequest::create($data + ['request_key' => $this->nextKey('FR', FeatureRequest::class, 'request_key'), 'status' => 'new']);
        FeatureRequestEvent::create(['request_id' => $model->id, 'event' => 'created', 'payload' => null, 'actor_id' => $request->user()->id, 'created_at' => now()]);
        $this->audit->record($request, 'platform.improvement.request_created', null, $model, ['key' => $model->request_key, 'priority' => $model->priority]);
        return response()->json(['data' => $this->serializeRequest($model)], 201);
    }

    public function requestUpdate(Request $request, FeatureRequest $requestModel): JsonResponse
    {
        $data = $request->validate([
            'title' => ['string', 'max:180'],
            'description' => ['string', 'max:10000'],
            'module' => ['nullable', 'string', 'max:80'],
            'business_impact' => ['nullable', 'string', 'max:4000'],
            'priority' => ['in:critical,high,medium,low'],
            'priority_change_reason' => ['required_with:priority', 'string', 'max:500'],
            'effort_estimate' => ['nullable', 'string', 'max:40'],
            'effort_estimated' => ['boolean'],
            'risk' => ['nullable', 'in:low,medium,high'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'linked_issues' => ['nullable', 'array'],
            'status' => ['required_with:status', 'in:'.implode(',', FeatureRequest::STATUSES)],
        ]);
        $previousStatus = $requestModel->status;
        $estimated = $data['effort_estimated'] ?? null;
        unset($data['effort_estimated']);
        if (isset($estimated)) {
            $data['effort_estimate'] = $estimated ? ($data['effort_estimate'] ?? $requestModel->effort_estimate) : null;
        }
        if (isset($data['status'])) {
            abort_unless(in_array($data['status'], FeatureRequest::TRANSITIONS[$previousStatus] ?? [], true), 422, "Status transition from {$previousStatus} to {$data['status']} is not allowed.");
        }
        if (isset($data['priority']) && $data['priority'] !== $requestModel->priority) {
            FeatureRequestEvent::create(['request_id' => $requestModel->id, 'event' => 'priority_changed', 'payload' => ['reason' => $data['priority_change_reason']], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        }
        $statusTo = $data['status'] ?? null;
        unset($data['priority_change_reason'], $data['status']);
        if ($statusTo !== null) {
            $data['status'] = $statusTo;
        }
        $requestModel->update($data);
        if ($statusTo) {
            FeatureRequestEvent::create(['request_id' => $requestModel->id, 'event' => 'status_changed', 'payload' => ['from' => $previousStatus, 'to' => $statusTo], 'actor_id' => $request->user()->id, 'created_at' => now()]);
            if ($statusTo === 'released') {
                $requestModel->update(['release_evidence' => $requestModel->release_evidence ?? []]);
            }
        }
        $this->audit->record($request, 'platform.improvement.request_updated', null, $requestModel, ['fields' => array_keys($data + ['status' => $statusTo])]);
        return response()->json(['data' => $this->serializeRequest($requestModel)]);
    }

    // ------------------------------------------------------------- roadmap

    public function roadmapIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.roadmap_listed', null);
        $query = RoadmapItem::query()->with('owner:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('visibility'), fn ($q, $v) => $q->where('visibility', $v))
            ->when($request->query('release'), fn ($q, $r) => $q->where('target_release_id', $r))
            ->orderBy('module')->orderBy('priority');
        $items = $query->limit(500)->get();
        return response()->json(['data' => [
            'items' => $items->map(fn (RoadmapItem $i) => $this->serializeRoadmapItem($i, brief: true))->all(),
            'by_status' => $this->columnCounts($items, 'status'),
        ]]);
    }

    public function roadmapStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'module' => ['nullable', 'string', 'max:80'],
            'source_type' => ['nullable', 'in:feature_request,defect,initiative'],
            'source_id' => ['nullable', 'uuid'],
            'priority' => ['required', 'in:critical,high,medium,low'],
            'status' => ['nullable', 'in:'.implode(',', RoadmapItem::STATUSES)],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'dependencies' => ['nullable', 'array'],
            'target_release_id' => ['nullable', 'uuid'],
            'target_quarter' => ['nullable', 'regex:/^\d{4}-Q[1-4]$/'],
            'completion_criteria' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', 'in:internal,customer_visible'],
            'customer_summary' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['source_type'] ??= 'initiative';
        $data['visibility'] ??= 'internal';
        abort_if(($data['visibility'] ?? null) === 'customer_visible' && blank($data['customer_summary'] ?? null), 422, 'Customer-visible roadmap items require a customer summary — internal targets are never published.');
        $item = RoadmapItem::create($data);
        $this->audit->record($request, 'platform.improvement.roadmap_item_created', null, $item, ['title' => $item->title, 'visibility' => $item->visibility]);
        return response()->json(['data' => $this->serializeRoadmapItem($item)], 201);
    }

    public function roadmapUpdate(Request $request, RoadmapItem $item): JsonResponse
    {
        $data = $request->validate([
            'title' => ['string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'module' => ['nullable', 'string', 'max:80'],
            'priority' => ['in:critical,high,medium,low'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['nullable', 'in:'.implode(',', RoadmapItem::STATUSES)],
            'dependencies' => ['nullable', 'array'],
            'target_release_id' => ['nullable', 'uuid'],
            'target_quarter' => ['nullable', 'regex:/^\d{4}-Q[1-4]$/'],
            'completion_criteria' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['nullable', 'in:internal,customer_visible'],
            'customer_summary' => ['nullable', 'string', 'max:2000'],
            'release_evidence' => ['nullable', 'array'],
        ]);
        $visibility = $data['visibility'] ?? $item->visibility;
        abort_if($visibility === 'customer_visible' && blank($data['customer_summary'] ?? $item->customer_summary), 422, 'Customer-visible roadmap items require a customer summary.');
        if (($data['status'] ?? null) === 'released') {
            abort_unless($this->releaseEvidenceExists($data, $item), 422, 'Marking a roadmap item released requires release evidence (linked deployed release).');
        }
        $item->update($data);
        $this->audit->record($request, 'platform.improvement.roadmap_item_updated', null, $item, ['fields' => array_keys($data)]);
        return response()->json(['data' => $this->serializeRoadmapItem($item)]);
    }

    private function releaseEvidenceExists(array $data, RoadmapItem $item): bool
    {
        $target = $data['target_release_id'] ?? $item->target_release_id;
        if (! $target) {
            return false;
        }
        $release = ReleaseRecord::find($target);
        return $release !== null && in_array($release->status, ['deployed', 'rolled_back'], true);
    }

    // ------------------------------------------------------------- defects

    public function defectsIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.defects_listed', null);
        $query = DefectRecord::query()->with('assignee:id,name')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->when($request->query('module'), fn ($q, $m) => $q->where('module', $m))
            ->when($request->query('environment'), fn ($q, $e) => $q->where('environment', $e))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'ilike', "%{$s}%")->orWhere('defect_number', 'ilike', "%{$s}%")))
            ->orderByRaw("CASE severity WHEN 'P0' THEN 0 WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 ELSE 3 END")->orderByDesc('created_at');
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        $page->getCollection()->transform(fn (DefectRecord $d) => $this->serializeDefect($d, brief: true));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function defectShow(Request $request, DefectRecord $defect): JsonResponse
    {
        $events = DefectEvent::where('defect_id', $defect->id)->orderByDesc('created_at')->limit(100)->get();
        return response()->json(['data' => $this->serializeDefect($defect->load('assignee:id,name') ) + [
            'events' => $events->map(fn ($e) => ['event' => $e->event, 'payload' => $e->payload, 'actor_id' => $e->actor_id, 'created_at' => $e->created_at?->toIso8601String()])->all(),
            'possible_transitions' => DefectRecord::TRANSITIONS[$defect->status] ?? [],
            'source_feedback' => $defect->source_feedback_id ? ProductFeedback::find($defect->source_feedback_id)?->only(['id', 'feedback_number', 'subject']) : null,
            'source_incident' => $defect->source_incident_id ? MonitoringIncident::find($defect->source_incident_id)?->only(['id', 'title', 'severity', 'status']) : null,
            'source_ticket' => $defect->source_ticket_id ? ['id' => $defect->source_ticket_id, 'endpoint' => '/api/v1/platform/support/tickets/{id}'] : null,
        ]]);
    }

    public function defectStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'module' => ['nullable', 'string', 'max:80'],
            'environment' => ['required', 'in:local,staging,production'],
            'severity' => ['required', 'in:'.implode(',', DefectRecord::SEVERITIES)],
            'reproduction_steps' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:2000'],
            'actual_result' => ['nullable', 'string', 'max:2000'],
            'error_summary' => ['nullable', 'string', 'max:500'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'source_feedback_id' => ['nullable', 'uuid'],
            'source_incident_id' => ['nullable', 'uuid'],
            'source_ticket_id' => ['nullable', 'uuid'],
            'related_change_ref' => ['nullable', 'string', 'max:120'],
            'related_test_ref' => ['nullable', 'string', 'max:120'],
        ]);
        $defect = DefectRecord::create($data + ['defect_number' => $this->nextKey('DEF', DefectRecord::class, 'defect_number'), 'status' => $data['assignee_id'] ?? null ? 'assigned' : 'open']);
        DefectEvent::create(['defect_id' => $defect->id, 'event' => 'created', 'payload' => ['severity' => $defect->severity], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        $this->audit->record($request, 'platform.improvement.defect_created', null, $defect, ['severity' => $defect->severity, 'source' => $defect->source_feedback_id ? 'feedback' : ($defect->source_incident_id ? 'incident' : 'manual')]);
        return response()->json(['data' => $this->serializeDefect($defect)], 201);
    }

    public function defectUpdate(Request $request, DefectRecord $defect): JsonResponse
    {
        $data = $request->validate([
            'title' => ['string', 'max:180'],
            'module' => ['nullable', 'string', 'max:80'],
            'environment' => ['in:local,staging,production'],
            'severity' => ['in:'.implode(',', DefectRecord::SEVERITIES)],
            'severity_change_reason' => ['required_with:severity', 'string', 'max:500'],
            'reproduction_steps' => ['nullable', 'string', 'max:5000'],
            'expected_result' => ['nullable', 'string', 'max:2000'],
            'actual_result' => ['nullable', 'string', 'max:2000'],
            'error_summary' => ['nullable', 'string', 'max:500'],
            'root_cause' => ['nullable', 'string', 'max:4000'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'related_change_ref' => ['nullable', 'string', 'max:120'],
            'related_test_ref' => ['nullable', 'string', 'max:120'],
            'fix_release_id' => ['nullable', 'uuid'],
            'fix_version' => ['nullable', 'string', 'max:60'],
            'retest_result' => ['nullable', 'in:pass,fail,blocked'],
            'status' => ['nullable', 'in:'.implode(',', DefectRecord::STATUSES)],
            'close_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $defect->status;
        $previousSeverity = $defect->severity;
        $to = $data['status'] ?? null;

        // Severity changes are audited decisions; P0/P1 downgrades need a documented reason.
        if (isset($data['severity']) && $data['severity'] !== $previousSeverity) {
            DefectEvent::create(['defect_id' => $defect->id, 'event' => 'severity_changed', 'payload' => ['from' => $previousSeverity, 'to' => $data['severity'], 'reason' => $data['severity_change_reason']], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        }
        unset($data['severity_change_reason']);

        if ($to !== null) {
            abort_unless(in_array($to, DefectRecord::TRANSITIONS[$previousStatus] ?? [], true), 422, "Defect transition from {$previousStatus} to {$to} is not allowed.");

            if ($to === 'verified') {
                abort_unless(($data['retest_result'] ?? $defect->retest_result) === 'pass', 422, 'A defect may only be verified after a passing retest.');
                $data['retest_verified_by'] = $request->user()->id;
                $data['retest_verified_at'] = now();
            }
            if ($to === 'closed') {
                abort_unless($previousStatus === 'verified', 422, 'Only verified defects may be closed — committed code alone is not enough.');
                abort_unless(filled($data['root_cause'] ?? $defect->root_cause), 422, 'Closing a defect requires a recorded root cause.');
                if (in_array($previousSeverity, ['P0', 'P1'], true) && in_array($data['severity'] ?? $previousSeverity, ['P0', 'P1'], true)) {
                    abort_unless(filled($data['fix_release_id'] ?? $defect->fix_release_id) || filled($data['fix_version'] ?? $defect->fix_version) || filled($data['related_change_ref'] ?? $defect->related_change_ref), 422, 'Closing a P0/P1 defect requires the fix version, fix release or related code change reference.');
                }
                $data['closed_at'] = now();
            }
            if ($previousStatus === 'closed' && $to === 'open') {
                abort_unless(filled($data['close_reason'] ?? null) || filled($request->input('reopen_reason')), 422, 'Reopening a closed defect requires a regression reason.');
                $data['reopened_count'] = $defect->reopened_count + 1;
                $data['closed_at'] = null;
            }
        }

        // A failed retest reopens the defect; it must not stay verified or fixed.
        if (isset($data['retest_result']) && $data['retest_result'] === 'fail' && $to === null && in_array($previousStatus, ['ready_for_retest', 'verified'], true)) {
            abort_unless(filled($request->input('reopen_reason')), 422, 'A failed retest must reopen the defect — include a regression reason.');
            $data['status'] = 'open';
            $data['reopened_count'] = $defect->reopened_count + 1;
            $data['retest_verified_at'] = null;
            $to = 'open';
        }

        unset($data['close_reason']);
        $attributes = $data;

        if ($to !== null && $to !== $previousStatus) {
            $attributes['status'] = $to;
            DefectEvent::create(['defect_id' => $defect->id, 'event' => 'status_changed', 'payload' => ['from' => $previousStatus, 'to' => $to, 'retest_result' => $attributes['retest_result'] ?? null], 'actor_id' => $request->user()->id, 'created_at' => now()]);
            if (isset($attributes['reopened_count']) && $attributes['reopened_count'] > $defect->reopened_count) {
                DefectEvent::create(['defect_id' => $defect->id, 'event' => 'reopened', 'payload' => null, 'actor_id' => $request->user()->id, 'created_at' => now()]);
            }
        }

        abort_unless($attributes !== [], 422, 'No recognized fields to update.');
        $defect->update($attributes);
        $this->audit->record($request, 'platform.improvement.defect_updated', null, $defect, ['status' => $defect->status, 'severity' => $defect->severity, 'fields' => array_keys($attributes)]);
        return response()->json(['data' => $this->serializeDefect($defect->fresh())]);
    }

    // ------------------------------------------------------------ rollouts

    public function rolloutsIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.rollouts_listed', null);
        $rollouts = FeatureRollout::query()->with(['flag:id,key,description,default_enabled,scope,review_at,is_sensitive'])
            ->when($request->query('stage'), fn ($q, $s) => $q->where('stage', $s))->orderBy('created_at')->get();
        return response()->json(['data' => [
            'rollouts' => $rollouts->map(fn (FeatureRollout $r) => $this->serializeRollout($r))->all(),
            'flag_authority_note' => 'Feature flags never bypass authorization: authentication, membership, RBAC, entitlements, ownership and tenant scope are checked independently on every protected operation.',
        ]]);
    }

    public function rolloutStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'flag_id' => ['required', 'uuid', 'exists:feature_flags,id'],
            'scope' => ['required', 'in:global,tenant,user'],
            'stage' => ['required', 'in:'.implode(',', FeatureRollout::STAGES)],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'audience' => ['nullable', 'array'],
            'rollout_percentage' => ['nullable', 'integer', 'between:0,100'],
            'review_at' => ['nullable', 'date'],
            'monitoring_link' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless(FeatureRollout::where('feature_flag_id', $data['flag_id'])->doesntExist(), 422, 'This feature flag already has a rollout record.');
        $rollout = FeatureRollout::create(['feature_flag_id' => $data['flag_id']] + collect($data)->except('flag_id')->all() + ['is_active' => true]);
        FeatureFlag::where('id', $data['flag_id'])->update(['scope' => $data['scope']]);
        FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'rollout_registered', 'payload' => $data, 'actor_id' => $request->user()->id, 'created_at' => now()]);
        $this->audit->record($request, 'platform.improvement.rollout_registered', null, $rollout, ['stage' => $rollout->stage]);
        return response()->json(['data' => $this->serializeRollout($rollout->load('flag'))], 201);
    }

    public function rolloutUpdate(Request $request, FeatureRollout $rollout): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['nullable', 'in:'.implode(',', FeatureRollout::STAGES)],
            'scope' => ['nullable', 'in:global,tenant,user'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'audience' => ['nullable', 'array'],
            'rollout_percentage' => ['nullable', 'integer', 'between:0,100'],
            'review_at' => ['nullable', 'date'],
            'monitoring_link' => ['nullable', 'string', 'max:255'],
            'approval' => ['nullable', 'boolean'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $previousStage = $rollout->stage;
        $to = $data['stage'] ?? null;

        if ($to !== null && in_array($to, ['pilot', 'wider_rollout', 'general_availability'], true) && $to !== $previousStage) {
            abort_unless(($data['approval'] ?? false) === true, 422, 'Advancing a rollout to pilot, wider rollout or general availability requires explicit recorded approval.');
            $rollout->update(['approval_status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'rollout_approved', 'payload' => ['note' => $data['approval_note'] ?? null], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        }
        $attributes = collect($data)->except(['stage', 'approval', 'approval_note'])->all();

        if (isset($data['is_active'])) {
            if ($data['is_active'] === false) {
                FeatureFlag::where('id', $rollout->feature_flag_id)->update(['default_enabled' => false]);
                FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'flag_disabled', 'payload' => null, 'actor_id' => $request->user()->id, 'created_at' => now()]);
            }
        }
        if ($to !== null && $to !== $previousStage) {
            FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'stage_changed', 'payload' => ['from' => $previousStage, 'to' => $to], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        }
        if ($attributes !== [] || $to !== null) {
            if ($to !== null) {
                $attributes['stage'] = $to;
            }
            $rollout->update($attributes);
        }
        if ($to !== null) {
            FeatureFlag::where('id', $rollout->feature_flag_id)->update(['rollout_stage' => $to]);
        }
        $flag = FeatureFlag::find($rollout->feature_flag_id);
        $this->audit->record($request, 'platform.improvement.rollout_updated', null, $rollout, ['stage' => $rollout->stage, 'flag_key' => $flag?->key]);
        return response()->json(['data' => $this->serializeRollout($rollout->fresh('flag'))]);
    }

    public function rolloutKillSwitch(Request $request, FeatureRollout $rollout): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        // Kill switch only changes flag/rollout state; it never mutates stored business data, and
        // data migrations for flagged features must be backward compatible (see FEATURE_FLAG_ROLLOUT.md).
        FeatureFlag::where('id', $rollout->feature_flag_id)->update(['default_enabled' => false]);
        $rollout->update(['stage' => 'disabled', 'is_active' => false]);
        TenantFeatureFlag::where('feature_flag_id', $rollout->feature_flag_id)->update(['enabled' => false]);
        FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'kill_switch_engaged', 'payload' => ['reason' => $data['reason']], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        $this->audit->record($request, 'platform.improvement.rollout_kill_switch', null, $rollout, ['reason' => $data['reason']]);
        return response()->json(['data' => $this->serializeRollout($rollout->fresh('flag'))]);
    }

    public function rolloutTenants(Request $request, FeatureRollout $rollout): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'enabled' => ['required', 'boolean'],
        ]);
        TenantFeatureFlag::updateOrCreate(
            ['tenant_id' => $data['tenant_id'], 'feature_flag_id' => $rollout->feature_flag_id],
            ['enabled' => $data['enabled']]
        );
        FeatureRolloutEvent::create(['rollout_id' => $rollout->id, 'event' => 'tenant_flag_set', 'payload' => ['tenant_id' => $data['tenant_id'], 'enabled' => $data['enabled']], 'actor_id' => $request->user()->id, 'created_at' => now()]);
        $this->audit->record($request, 'platform.improvement.rollout_tenant_flag_set', $data['tenant_id'], $rollout, ['enabled' => $data['enabled']]);
        return response()->json(['data' => $this->serializeRollout($rollout->fresh('flag'))]);
    }

    // ------------------------------------------------------------ releases

    public function releasesIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.releases_listed', null);
        $records = ReleaseRecord::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('environment'), fn ($q, $e) => $q->where('environment', $e))
            ->orderByDesc('created_at')->limit(100)->get();
        return response()->json(['data' => [
            'records' => $records->map(fn (ReleaseRecord $r) => $this->serializeRelease($r))->all(),
            'status_note' => 'Do not mark Deployed until actual deployment evidence exists: transition to deployed requires a deployment reference.',
        ]]);
    }

    public function releaseShow(Request $request, ReleaseRecord $record): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.release_viewed', null, $record, ['version' => $record->version]);
        return response()->json(['data' => $this->serializeRelease($record)]);
    }

    public function releaseUpdate(Request $request, ReleaseRecord $record): JsonResponse
    {
        $data = $request->validate([
            'release_id' => ['nullable', 'string', 'max:60'],
            'linked_features' => ['nullable', 'array'],
            'linked_fixes' => ['nullable', 'array'],
            'release_notes' => ['nullable', 'string', 'max:8000'],
            'release_notes_my' => ['nullable', 'string', 'max:8000'],
            'release_notes_sections' => ['nullable', 'array'],
            'known_issues' => ['nullable', 'string', 'max:4000'],
            'audience' => ['nullable', 'in:platform,business,both'],
            'customer_visible' => ['nullable', 'boolean'],
            'deployment_ref' => ['nullable', 'string', 'max:255'],
            'deployed_at' => ['nullable', 'date'],
            'status' => ['nullable', 'in:'.implode(',', ReleaseRecord::STATUSES)],
            'rollout_approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $previousStatus = $record->status;
        $to = $data['status'] ?? null;
        if ($to !== null) {
            abort_unless($to !== $previousStatus, 422, 'Release status is unchanged.');
            abort_unless(in_array($to, ReleaseRecord::TRANSITIONS[$previousStatus] ?? [], true), 422, "Release status transition from {$previousStatus} to {$to} is not allowed.");
            if ($to === 'approved') {
                abort_unless($previousStatus === 'qa_passed', 422, 'Release approval requires a passing QA status first.');
                $data['approval_state'] = 'approved';
                $data['approved_by'] = $request->user()->id;
                $data['approved_at'] = now();
            }
            if ($to === 'deployed') {
                abort_unless(filled($data['deployment_ref'] ?? $record->deployment_ref), 422, 'Marking a release deployed requires actual deployment evidence (deployment reference).');
                $data['deployed_at'] = $data['deployed_at'] ?? now();
            }
            if ($previousStatus === 'deployed' && $to === 'rolled_back') {
                abort_unless(filled($record->rollback_reference), 422, 'Rollbacks require a recorded rollback reference.');
            }
        }
        $publishing = (bool) ($data['customer_visible'] ?? false);
        if ($publishing) {
            abort_unless(in_array($data['audience'] ?? $record->audience, ['business', 'both'], true), 422, 'Customer-visible release notes must target audiences business or both.');
            abort_unless(filled($data['release_notes'] ?? $record->release_notes), 422, 'Publishing requires English release notes.');
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $attributes = $data;
        if ($to !== null) {
            $attributes['status'] = $to;
        }
        $record->update(array_filter($attributes, fn ($v) => $v !== null));
        $this->audit->record($request, 'platform.improvement.release_updated', null, $record, ['from' => $previousStatus, 'to' => $to, 'fields' => array_keys($attributes)]);
        return response()->json(['data' => $this->serializeRelease($record->fresh())]);
    }

    // ---------------------------------------------------------- reliability

    public function reliability(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.reliability_viewed', null);
        $slos = ServiceLevelObjective::with('approver:id,name')->orderBy('name')->get();
        return response()->json(['data' => [
            'slos' => $slos->map(fn (ServiceLevelObjective $slo) => $this->evaluateSlo($slo))->all(),
            'measurement_model' => [
                'source' => 'monitoring_observations (Phase 7)',
                'window' => 'per-SLO window_days ending now',
                'availability_formula' => '100 * observations_with_success_status / total_observations_for_source',
                'error_budget_formula' => '(1 - target_availability) * window duration; used budget approximated as down-observations x median observation interval; unknown when interval cannot be derived',
                'no_data' => 'unknown — never fabricated',
            ],
            'incidents' => MonitoringIncident::whereIn('status', ['open', 'acknowledged'])->orderByDesc('started_at')->limit(5)
                ->get(['id', 'title', 'severity', 'status', 'started_at'])
                ->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'severity' => $i->severity, 'status' => $i->status, 'started_at' => $i->started_at?->toIso8601String()])->all(),
        ]]);
    }

    public function sloStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:service_level_objectives,name'],
            'sli_type' => ['required', 'in:'.implode(',', ServiceLevelObjective::SLI_TYPES)],
            'metric_source' => ['required', 'string', 'max:80'],
            'target' => ['required', 'numeric', 'between:0,100'],
            'window_days' => ['nullable', 'integer', 'between:1,365'],
            'criteria' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $slo = ServiceLevelObjective::create($data + ['window_days' => $data['window_days'] ?? 30, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $this->audit->record($request, 'platform.improvement.slo_defined', null, $slo, ['name' => $slo->name, 'target' => $slo->target]);
        return response()->json(['data' => $this->evaluateSlo($slo)], 201);
    }

    public function sloUpdate(Request $request, ServiceLevelObjective $slo): JsonResponse
    {
        $data = $request->validate([
            'target' => ['nullable', 'numeric', 'between:0,100'],
            'window_days' => ['nullable', 'integer', 'between:1,365'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $slo->update($data);
        $this->audit->record($request, 'platform.improvement.slo_updated', null, $slo, ['fields' => array_keys($data)]);
        return response()->json(['data' => $this->evaluateSlo($slo)]);
    }

    // ------------------------------------------------------------ quality

    public function quality(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.quality_viewed', null);
        return response()->json(['data' => $this->qualityMetrics()]);
    }

    private function qualityMetrics(): array
    {
        $deploymentsReleased = ReleaseRecord::whereIn('status', ['deployed', 'rolled_back'])->get(['status', 'environment', 'deployed_at', 'created_at']);
        $failedCount = $deploymentsReleased->whereIn('status', ['failed', 'rolled_back'])->count();
        $totalReleaseActions = max($deploymentsReleased->count(), 1); // denominator guard
        $changeFailRate = $deploymentsReleased->count() > 0 ? round(100 * $failedCount / $deploymentsReleased->count(), 1) : null;

        $openDefects = DefectRecord::whereIn('status', array_diff(DefectRecord::STATUSES, ['closed']))->count();
        $closedDefects = DefectRecord::where('status', 'closed')->count();
        $reopenedDefects = DefectRecord::where('status', 'closed')->where('reopened_count', '>', 0)->count();
        $productionDefects = DefectRecord::where('environment', 'production')->count();
        $allDefects = DefectRecord::count();

        return [
            'computed_at' => now()->toIso8601String(),
            'data_destinations' => ['release_records', 'defect_records', 'monitoring_error_groups'],
            'deployment_frequency' => $deploymentsReleased->count() > 0 ? [
                'available' => true,
                'monthly' => $deploymentsReleased->groupBy(fn ($r) => $r->deployed_at?->format('Y-m'))->map->count()->sortKeys()->all(),
            ] : ['available' => false, 'value' => 'unknown', 'reason' => 'no release records'],
            'change_lead_time' => ['available' => false, 'value' => 'unknown', 'reason' => 'requires CI/CD commit-to-deploy ingestion (not wired)'],
            'change_fail_rate' => $deploymentsReleased->count() > 0 ? ['available' => true, 'value' => $changeFailRate, 'unit' => '%'] : ['available' => false, 'value' => 'unknown'],
            'failed_deployment_recovery_time' => ['available' => false, 'value' => 'unknown', 'reason' => 'requires deployment start/end evidence from CI/CD'],
            'rework_rate' => ['available' => false, 'value' => 'unknown', 'reason' => 'requires pipeline rework events'],
            'automated_test_failure_trends' => ['available' => false, 'value' => 'unknown', 'reason' => 'CI run ingestion not wired'],
            'defect_reopen_rate' => $closedDefects > 0 ? ['available' => true, 'value' => round(100 * $reopenedDefects / $closedDefects, 1), 'unit' => '%', 'denominator' => $closedDefects] : ['available' => false, 'value' => 'unknown'],
            'defect_escape_rate' => $allDefects > 0 ? ['available' => true, 'value' => round(100 * $productionDefects / $allDefects, 1), 'unit' => '%'] : ['available' => false, 'value' => 'unknown'],
            'open_defects' => $openDefects,
            'commit_note' => 'Git commit counts are never used as a productivity metric.',
        ];
    }

    private function qualityTrend(): array
    {
        $openWeeks = [];
        $nowWeek = now();
        for ($i = 7; $i >= 0; $i--) {
            $start = $nowWeek->copy()->subWeeks($i)->startOfWeek();
            $end = $start->copy()->addWeek();
            $opened = DefectRecord::whereBetween('created_at', [$start, $end])->count();
            $closed = DefectRecord::whereBetween('closed_at', [$start, $end])->count();
            $openWeeks[] = ['week' => $start->format('Y-m-d'), 'opened' => $opened, 'closed' => $closed];
        }
        return ['weeks' => $openWeeks];
    }

    // --------------------------------------------------------- satisfaction

    public function satisfaction(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.satisfaction_viewed', null);
        $from = $request->query('from');
        $to = $request->query('to');
        $query = SatisfactionResponse::query()->when($from, fn ($q, $f) => $q->where('submitted_at', '>=', $f))->when($to, fn ($q, $t) => $q->where('submitted_at', '<=', $t));
        return response()->json(['data' => [
            'from' => $from, 'to' => $to,
            'summary' => $this->satisfactionSummary(query: null, from: $from, to: $to),
            'by_context' => DB::table('satisfaction_responses')->selectRaw('context, count(*) as responses, round(avg(score)::numeric, 2) as avg_score')->where('submitted_at', '>=', $from ?? now()->subDays(30))->groupBy('context')->get()->map(fn ($r) => ['context' => $r->context, 'responses' => (int) $r->responses, 'avg_score' => $r->avg_score !== null ? (float) $r->avg_score : null])->all(),
            'by_score' => DB::table('satisfaction_responses')->selectRaw('score, count(*) as responses')->where('submitted_at', '>=', $from ?? now()->subDays(30))->groupBy('score')->orderBy('score')->get()->map(fn ($r) => ['score' => (int) $r->score, 'responses' => (int) $r->responses])->all(),
            'denominator_policy' => 'Only submitted voluntary responses are counted. Nonresponses are reported as a separate count; no conversion rate from requests is inferred because request exposure is not instrumented.',
            'nonresponse_count' => 'unknown_denominator_not_measured',
            'csat_note' => 'CSAT average is computed only from real responses; zero responses render unknown.',
        ]]);
    }

    private function satisfactionSummary($query, ?string $from = null, ?string $to = null)
    {
        $base = SatisfactionResponse::query()
            ->when($from, fn ($q, $f) => $q->where('submitted_at', '>=', $f))
            ->when($to, fn ($q, $t) => $q->where('submitted_at', '<=', $t));
        $count = (clone $base)->count();
        $avg = (clone $base)->avg('score');
        return [
            'response_count' => $count,
            'avg_score' => $avg !== null ? round((float) $avg, 2) : null,
            'state' => $count === 0 ? 'unknown' : 'measured',
            'date_range' => ['from' => $from ?? $base->min('submitted_at'), 'to' => $to ?? $base->max('submitted_at')],
        ];
    }

    // ------------------------------------------------------------ maturity

    public function maturityIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.maturity_viewed', null);
        $assessments = MaturityAssessment::with('reviewer:id,name')->orderBy('dimension')->orderByDesc('assessed_at')->get();
        $latest = $assessments->groupBy('dimension')->map(fn ($rows) => $rows->first());
        return response()->json(['data' => [
            'assessments' => $latest->map(fn (MaturityAssessment $a) => $this->serializeAssessment($a))->values()->all(),
            'history' => $assessments->map(fn (MaturityAssessment $a) => $this->serializeAssessment($a, brief: true))->all(),
            'rubric' => ['not_assessed', 'initial', 'developing', 'managed', 'optimized'],
            'methodology_note' => 'Each dimension is assessed individually with evidence; no aggregate score is published without an approved transparent methodology, and security release gates stay independent of maturity scores.',
            'missing_dimensions' => collect(MaturityAssessment::DIMENSIONS)->diff($latest->keys())->values()->all(),
        ]]);
    }

    public function maturityStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dimension' => ['required', 'in:'.implode(',', MaturityAssessment::DIMENSIONS)],
            'period' => ['required', 'regex:/^\d{4}-(Q[1-4]|\d{2}-\d{2})$/'],
            'level' => ['required', 'in:'.implode(',', MaturityAssessment::LEVELS)],
            'evidence' => ['required', 'string', 'min:20', 'max:5000'],
            'gaps' => ['nullable', 'string', 'max:3000'],
            'improvement_actions' => ['nullable', 'string', 'max:3000'],
        ]);
        $data['reviewer_id'] = $request->user()->id;
        $data['assessed_at'] = now();
        $model = MaturityAssessment::updateOrCreate(['dimension' => $data['dimension'], 'period' => $data['period']], $data);
        $this->audit->record($request, 'platform.improvement.maturity_assessed', null, $model, ['dimension' => $data['dimension'], 'level' => $data['level'], 'period' => $data['period']]);
        return response()->json(['data' => $this->serializeAssessment($model->load('reviewer:id,name'))], 201);
    }

    // ------------------------------------------------------------- reviews

    public function reviewsIndex(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.improvement.reviews_listed', null);
        $query = ImprovementReview::query()->with(['owner:id,name', 'creator:id,name'])
            ->when($request->query('cadence'), fn ($q, $c) => $q->where('cadence', $c))
            ->orderByDesc('review_date');
        $page = $query->paginate(min((int) ($request->query('per_page', 20)), 50));
        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function reviewStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cadence' => ['required', 'in:'.implode(',', ImprovementReview::CADENCES)],
            'title' => ['required', 'string', 'max:180'],
            'findings' => ['nullable', 'string', 'max:5000'],
            'decisions' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:critical,high,medium,low'],
            'review_date' => ['required', 'date'],
            'evidence' => ['nullable', 'array'],
        ]);
        $model = ImprovementReview::create($data + ['priority' => $data['priority'] ?? 'medium', 'created_by' => $request->user()->id]);
        $this->audit->record($request, 'platform.improvement.review_recorded', null, $model, ['cadence' => $model->cadence]);
        return response()->json(['data' => $model->load(['owner:id,name', 'creator:id,name'])], 201);
    }

    // ----------------------------------------------------------- internals

    private function nextKey(string $prefix, string $modelClass, string $column): string
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $key = sprintf('%s-%s-%s', $prefix, now()->format('ymd'), strtoupper(Str::random(6)));
            if ($modelClass::where($column, $key)->doesntExist()) {
                return $key;
            }
        }
        return sprintf('%s-%s-%s', $prefix, now()->format('ymdHis'), strtoupper(Str::random(3)));
    }

    private function intMap($map): array
    {
        return collect($map)->map(fn ($v) => (int) $v)->all();
    }

    private function columnCounts($items, string $key): array
    {
        return $items->groupBy($key)->map->count()->all();
    }

    private function feedbackNotes(ProductFeedback $feedback, bool $internal): array
    {
        $query = FeedbackNote::where('feedback_id', $feedback->id)->with('author:id,name,email')->orderBy('created_at');
        $query->when(! $internal, fn ($q) => $q->where('visibility', 'public'));
        return $query->get()->map(fn (FeedbackNote $n) => [
            'id' => $n->id, 'author_type' => $n->author_type, 'visibility' => $n->visibility, 'body' => $n->body,
            'author' => $n->author?->only(['id', 'name', 'email']), 'created_at' => $n->created_at?->toIso8601String(),
        ])->all();
    }

    private function history(string $feedbackId): array
    {
        return FeedbackStatusHistory::where('feedback_id', $feedbackId)->with('actor:id,name')->orderBy('created_at')->get()
            ->map(fn (FeedbackStatusHistory $h) => [
                'from_status' => $h->from_status, 'to_status' => $h->to_status, 'reason' => $h->reason,
                'actor' => $h->actor?->only(['id', 'name']), 'created_at' => $h->created_at?->toIso8601String(),
            ])->all();
    }

    private function applyStatus(ProductFeedback $feedback, ?string $from, string $to, Request $request, ?string $reason = null): void
    {
        FeedbackStatusHistory::create([
            'tenant_id' => $feedback->tenant_id, 'feedback_id' => $feedback->id, 'from_status' => $from,
            'to_status' => $to, 'reason' => $reason, 'actor_id' => $request->user()->id, 'created_at' => now(),
        ]);
    }

    private function ticketSummary(?string $ticketId): ?array
    {
        if (! $ticketId) {
            return null;
        }
        $ticket = DB::table('support_tickets')->where('id', $ticketId)->first(['id', 'ticket_number', 'subject', 'status']);
        return $ticket ? ['id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'subject' => $ticket->subject, 'status' => $ticket->status] : null;
    }

    private function serializeFeedback(ProductFeedback $f, bool $brief = false): array
    {
        $data = [
            'id' => $f->id, 'feedback_number' => $f->feedback_number, 'tenant' => $f->tenant?->only(['id', 'name']),
            'category' => $f->category, 'module' => $f->module, 'subject' => $f->subject, 'status' => $f->status,
            'assigned_to' => $f->assignee?->only(['id', 'name']), 'submitted_by' => $f->submitter?->only(['id', 'name', 'email']),
            'duplicate_of' => $f->duplicateOf?->only(['id', 'feedback_number', 'subject']), 'has_attachments' => filled($f->attachments),
            'satisfaction_score' => $f->satisfaction_score, 'created_at' => $f->created_at?->toIso8601String(),
            'updated_at' => $f->updated_at?->toIso8601String(),
        ];
        if (! $brief) {
            $data += ['message' => $f->message, 'attachments' => $f->attachments, 'source' => $f->source,
                'support_ticket_id' => $f->support_ticket_id, 'resolution_outcome' => $f->resolution_outcome,
                'satisfaction_comment' => $f->satisfaction_comment, 'duplicate_of_id' => $f->duplicate_of_id];
        }
        return $data;
    }

    private function serializeRequest(FeatureRequest $r, bool $brief = false): array
    {
        $data = [
            'id' => $r->id, 'request_key' => $r->request_key, 'title' => $r->title, 'status' => $r->status,
            'module' => $r->module, 'priority' => $r->priority, 'owner' => $r->owner?->only(['id', 'name']),
            'effort_estimate' => $r->effort_estimate ?? 'not_estimated', 'risk' => $r->risk,
            'target_release_id' => $r->target_release_id, 'created_at' => $r->created_at?->toIso8601String(),
        ];
        if (! $brief) {
            $data += ['description' => $r->description, 'origin' => $r->origin, 'business_impact' => $r->business_impact,
                'requester_reference' => $r->requester_reference, 'linked_issues' => $r->linked_issues,
                'release_evidence' => $r->release_evidence, 'target_release_notes' => $r->target_release_notes,
                'updated_at' => $r->updated_at?->toIso8601String()];
        }
        return $data;
    }

    private function serializeRoadmapItem(RoadmapItem $i, bool $brief = false): array
    {
        $data = [
            'id' => $i->id, 'title' => $i->title, 'module' => $i->module, 'status' => $i->status,
            'priority' => $i->priority, 'owner' => $i->owner?->only(['id', 'name']), 'source_type' => $i->source_type,
            'source_id' => $i->source_id, 'target_release_id' => $i->target_release_id, 'target_quarter' => $i->target_quarter,
            'visibility' => $i->visibility, 'created_at' => $i->created_at?->toIso8601String(),
        ];
        if (! $brief) {
            $data += ['description' => $i->description, 'dependencies' => $i->dependencies,
                'completion_criteria' => $i->completion_criteria, 'release_evidence' => $i->release_evidence,
                'customer_summary' => $i->customer_summary];
        }
        return $data;
    }

    private function serializeDefect(DefectRecord $d, bool $brief = false): array
    {
        $data = [
            'id' => $d->id, 'defect_number' => $d->defect_number, 'title' => $d->title, 'module' => $d->module,
            'environment' => $d->environment, 'severity' => $d->severity, 'status' => $d->status,
            'assignee' => $d->assignee?->only(['id', 'name']), 'fix_version' => $d->fix_version,
            'retest_result' => $d->retest_result, 'reopened_count' => $d->reopened_count,
            'closed_at' => $d->closed_at?->toIso8601String(), 'created_at' => $d->created_at?->toIso8601String(),
        ];
        if (! $brief) {
            $data += ['reproduction_steps' => $d->reproduction_steps, 'expected_result' => $d->expected_result,
                'actual_result' => $d->actual_result, 'error_summary' => $d->error_summary, 'root_cause' => $d->root_cause,
                'related_change_ref' => $d->related_change_ref, 'related_test_ref' => $d->related_test_ref,
                'fix_release_id' => $d->fix_release_id, 'retest_verified_at' => $d->retest_verified_at?->toIso8601String(),
                'retest_verified_by' => $d->retest_verified_by, 'source_feedback_id' => $d->source_feedback_id,
                'source_incident_id' => $d->source_incident_id, 'source_ticket_id' => $d->source_ticket_id,
                'updated_at' => $d->updated_at?->toIso8601String()];
        }
        return $data;
    }

    private function serializeRollout(FeatureRollout $r): array
    {
        $flag = $r->flag;
        return [
            'id' => $r->id, 'flag' => $flag ? ['id' => $flag->id, 'key' => $flag->key, 'description' => $flag->description,
                'default_enabled' => $flag->default_enabled, 'scope' => $flag->scope, 'review_at' => $flag->review_at,
                'is_sensitive' => $flag->is_sensitive] : null,
            'stage' => $r->stage, 'scope' => $r->scope, 'owner' => $r->owner?->only(['id', 'name']),
            'audience' => $r->audience, 'rollout_percentage' => $r->rollout_percentage,
            'approval_status' => $r->approval_status, 'approved_at' => $r->approved_at?->toIso8601String(),
            'review_at' => $r->review_at, 'monitoring_link' => $r->monitoring_link, 'is_active' => $r->is_active,
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }

    private function serializeRelease(ReleaseRecord $r): array
    {
        return ['id' => $r->id, 'release_id' => $r->release_id, 'version' => $r->version, 'environment' => $r->environment,
            'status' => $r->status, 'release_notes' => $r->release_notes, 'release_notes_my' => $r->release_notes_my,
            'release_notes_sections' => $r->release_notes_sections, 'known_issues' => $r->known_issues,
            'linked_features' => $r->linked_features, 'linked_fixes' => $r->linked_fixes,
            'audience' => $r->audience, 'customer_visible' => $r->customer_visible, 'published_at' => $r->published_at?->toIso8601String(),
            'deployment_ref' => $r->deployment_ref, 'approval_state' => $r->approval_state,
            'deployed_at' => $r->deployed_at?->toIso8601String(), 'rollback_reference' => $r->rollback_reference,
            'recorded_at' => $r->created_at?->toIso8601String()];
    }

    private function serializeAssessment(MaturityAssessment $a, bool $brief = false): array
    {
        $data = ['id' => $a->id, 'dimension' => $a->dimension, 'period' => $a->period, 'level' => $a->level,
            'reviewer' => $a->reviewer?->only(['id', 'name']), 'assessed_at' => $a->assessed_at?->toIso8601String()];
        if (! $brief) {
            $data += ['evidence' => $a->evidence, 'gaps' => $a->gaps, 'improvement_actions' => $a->improvement_actions];
        }
        return $data;
    }

    private function evaluateSlo(ServiceLevelObjective $slo): array
    {
        $windowStart = now()->copy()->subDays($slo->window_days);
        $base = MonitoringObservation::where(fn ($q) => $q->where('service', $slo->metric_source)->orWhere('check_key', $slo->metric_source))
            ->where('observed_at', '>=', $windowStart);
        $total = (clone $base)->count();
        $criteria = $slo->criteria ?? [];

        $observed = null;
        $status = 'unknown';
        $unknownReason = $total === 0 ? 'no monitoring observations in window' : null;
        $badCount = null;
        $intervalSeconds = null;

        if ($total > 0) {
            $successCounts = (clone $base)->selectRaw("status, count(*) as c")->groupBy('status')->pluck('c', 'status');
            if ($slo->sli_type === 'latency') {
                $threshold = $criteria['latency_threshold_ms'] ?? null;
                if ($threshold === null) {
                    $unknownReason = 'latency SLO requires criteria.latency_threshold_ms';
                } else {
                    $underCount = (clone $base)->where('response_time_ms', '<=', $threshold)->count();
                    $observed = round(100 * $underCount / $total, 3);
                }
            } elseif ($slo->sli_type === 'availability') {
                $successStatuses = $criteria['success_statuses'] ?? ['healthy', 'degraded'];
                $successCount = $successCounts->filter(fn ($v, $k) => in_array($k, $successStatuses, true))->sum();
                $observed = round(100 * $successCount / $total, 3);
                $badCount = $total - $successCount;
            } else {
                $successCount = $successCounts['healthy'] ?? 0;
                $observed = round(100 * $successCount / $total, 3);
                $badCount = $total - $successCount;
            }
            $interval = DB::table('monitoring_observations')
                ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY gap) as median_gap')
                ->fromSub(MonitoringObservation::where(fn ($q) => $q->where('service', $slo->metric_source)->orWhere('check_key', $slo->metric_source))->where('observed_at', '>=', $windowStart)->selectRaw('EXTRACT(EPOCH FROM (observed_at - LAG(observed_at) OVER (ORDER BY observed_at))) as gap'), 'gaps')
                ->value('median_gap');
            if ($interval !== null && (float) $interval > 0) {
                $intervalSeconds = (float) $interval;
            }
        }

        $errorBudget = null;
        $budgetUsed = null;
        if ($slo->sli_type === 'availability') {
            $windowSeconds = $slo->window_days * 86400;
            $errorBudget = round((1 - (float) $slo->target / 100) * $windowSeconds, 0);
            if ($badCount !== null && $intervalSeconds !== null) {
                $budgetUsed = round($badCount * $intervalSeconds, 0);
            }
        }

        if ($observed !== null) {
            $status = (float) $observed >= (float) $slo->target ? 'meeting' : (((float) $observed >= (float) $slo->target - 1.0) ? 'at_risk' : 'violated');
            $slo->status = $status;
            $slo->timestamps = false;
            $slo->save();
        }

        return ['id' => $slo->id, 'name' => $slo->name, 'sli_type' => $slo->sli_type, 'metric_source' => $slo->metric_source,
            'target' => (float) $slo->target, 'window_days' => $slo->window_days, 'is_active' => $slo->is_active,
            'approved_by' => $slo->approver?->only(['id', 'name']), 'criteria' => $slo->criteria,
            'observed' => $observed, 'status' => $observed !== null ? $status : 'unknown',
            'observations_in_window' => $total,
            'error_budget' => $errorBudget !== null ? ['seconds' => (int) $errorBudget, 'used_seconds' => $budgetUsed, 'used_percent' => $budgetUsed !== null ? round(100 * $budgetUsed / max($errorBudget, 1), 2) : null] : null,
            'not_configured_reason' => $unknownReason,
            'description' => $slo->description];
    }

    private function reliabilitySummary(): array
    {
        $services = collect($this->monitoring->overview()['services'] ?? []);
        return [
            'services' => $services->map(fn ($s) => ['service' => $s['service'], 'status' => $s['status'] ?? 'unknown'])->values()->all(),
            'path' => '/api/v1/platform/improvement/reliability',
        ];
    }
}
