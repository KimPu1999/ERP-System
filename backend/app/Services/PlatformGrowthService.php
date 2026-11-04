<?php

namespace App\Services;

use App\Models\{OnboardingProgress, PlatformAuditLog, PlatformPayment, SupportTicket, Tenant, TenantMembership, TenantModule, TenantSubscription};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 growth analytics.
 *
 * Every metric is computed from durable backend records only: tenants,
 * memberships, onboarding_progress rows, immutable platform audit events,
 * tenant subscriptions, payments and support tickets.
 *
 * Rules enforced by this service:
 * - Current status is never presented as historical data.
 * - Missing event/history sources report `available: false` with a reason.
 * - Each business is counted once; setup retries are deduplicated by tenant id.
 * - Tenant financial records, transaction contents and personal documents
 *   are never included in these aggregates.
 */
final class PlatformGrowthService
{
    /** Cohorts with no completion record and no recorded activity in this window are flagged as possibly abandoned. */
    public const ABANDONMENT_STALL_DAYS = 14;

    /** Mirrors the required step list of OnboardingProgressService so analytics never invents its own criteria. */
    private const REQUIRED_STEPS = [
        'email_verification', 'business_approval', 'company_profile', 'accounting_defaults',
        'first_branch', 'catalog_references', 'product_categories', 'first_product', 'workspace_readiness',
    ];

    private const SUPPORT_OPEN_STATUSES = ['open', 'in_progress', 'waiting_customer', 'waiting_support'];

    /** Audit action prefixes per module area (documented taxonomy). */
    private const ACTION_TAXONOMY = [
        'catalog' => ['product_', 'unit_', 'price_list'],
        'inventory' => ['inventory.', 'warehouse', 'stock_'],
        'purchasing' => ['purchase_', 'goods_receipt', 'supplier_'],
        'sales' => ['sales.', 'quotation', 'fulfillment'],
        'accounting' => ['journal_', 'fiscal', 'account_', 'ledger', 'financial_report'],
        'payroll' => ['payroll', 'employee_', 'attendance'],
        'crm' => ['customer', 'lead', 'opportunity', 'campaign'],
    ];

    public function __construct(private readonly PlatformAnalyticsService $analytics) {}

    public function overview(Request $request): array
    {
        $window = $this->window($request);
        return [
            'filters' => $this->filterPayload($window),
            'kpis' => [
                'registered_in_window' => Tenant::where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->count(),
                'total_businesses' => Tenant::count(),
                'businesses_by_status' => $this->statusCounts(),
                'onboarding_completions_in_window' => $this->completionsInWindow($window)->count(),
                'total_onboarding_completions' => OnboardingProgress::query()->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed')->count(),
                'trial_subscriptions' => TenantSubscription::where('state', 'trialing')->count(),
                'failed_payment_tenants_in_window' => $this->distinctFailedPaymentTenantIds($window)->count(),
                'open_support_tickets' => SupportTicket::whereIn('status', self::SUPPORT_OPEN_STATUSES)->count(),
            ],
            'unavailable' => ['trial_to_paid_conversion' => ['available' => false, 'reason' => self::transitionLedgerReason()]],
            'definitions' => self::overviewDefinitions(),
            'timezone' => PlatformAnalyticsService::REPORTING_TIMEZONE,
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    public function onboarding(Request $request): array
    {
        $window = $this->window($request);
        $query = Tenant::query()->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc']);
        if ($request->filled('plan_id')) $query->whereHas('subscription', fn (Builder $s) => $s->where('plan_id', $request->string('plan_id')->toString()));
        if ($request->filled('business_type')) $query->whereHas('companies', fn (Builder $c) => $c->where('business_type', $request->string('business_type')->toString()));
        $cohort = $query->get(['id', 'created_at']);
        $cohortIds = $cohort->modelKeys();
        $registered = count($cohortIds);

        $verified = $registered === 0 ? 0 : TenantMembership::query()->whereIn('tenant_id', $cohortIds)->where('status', 'active')
            ->whereHas('user', fn (Builder $q) => $q->whereNotNull('email_verified_at'))->distinct('tenant_id')->count('tenant_id');
        $started = $registered === 0 ? 0 : OnboardingProgress::whereIn('tenant_id', $cohortIds)->distinct()->count('tenant_id');
        $completionRows = $registered === 0 ? collect() : OnboardingProgress::whereIn('tenant_id', $cohortIds)
            ->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed')->get(['tenant_id', 'completed_at']);
        [$approved, $approvedAvailable] = $this->approvedCount($cohortIds);

        return [
            'filters' => $this->filterPayload($window),
            'cohort_definition' => $this->cohortDefinition($window),
            'metrics' => [
                'registrations' => $registered,
                'verified_registrations' => ['value' => $verified, 'definition' => 'Cohort tenants with at least one active membership whose user email is verified as of this request. Verified-at timing is not retained historically, so the value reflects verification now.'],
                'approved_businesses' => ['value' => $approved, 'available' => $approvedAvailable, 'reason' => $approvedAvailable ? null : $this->auditCoverageNote(), 'definition' => 'Distinct cohort tenants with an immutable platform.tenant.status_changed audit event recorded with to=active.'],
                'onboarding_started' => ['value' => $started, 'definition' => 'Cohort tenants with at least one recorded onboarding step. Rows exist only after verified step completion, so a retried step never counts twice.'],
                'onboarding_completed' => ['value' => $completionRows->count(), 'definition' => 'Cohort tenants with a completed workspace_readiness progress row; written only when authoritative backend data passes every required check.'],
                'activation_rate_percent' => ['value' => $registered === 0 ? null : (int) round($completionRows->count() / max(1, $registered) * 100), 'available' => $registered > 0, 'reason' => $registered === 0 ? 'No registered businesses in the selected cohort.' : null, 'definition' => 'workspace_readiness completions divided by registered cohort businesses.'],
                'required_setup_completion_rate' => $this->requiredStepCompletionRate($cohortIds, $registered),
                'abandoned_or_stalled' => ['value' => $this->stalledTenantCount($cohortIds), 'definition' => 'Cohort tenants with no completed workspace_readiness row and no recorded onboarding progress within the last '.self::ABANDONMENT_STALL_DAYS.' days. Counted once per tenant.'],
                'time_to_activation' => $this->timeToActivation($cohort, $completionRows),
            ],
            'trend' => $this->registrationActivationTrend($window),
            'privacy' => 'Growth reporting aggregates lifecycle signals only and never exposes tenant transactions, financial records, or personal documents.',
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    public function adoption(Request $request): array
    {
        $window = $this->window($request);
        $modules = TenantModule::query()->where('enabled', true)->select('module_key')->selectRaw('COUNT(*) AS count')->groupBy('module_key')->orderBy('module_key')->get()
            ->map(fn ($row): array => ['module_key' => $row->module_key, 'businesses_enabled' => (int) $row->count])->values()->all();
        return [
            'filters' => $this->filterPayload($window),
            'enabled_modules' => ['data' => $modules, 'definition' => 'Tenants with the optional module enabled. Module enabled is a configuration state and is explicitly not module usage.'],
            'recorded_module_actions' => [
                'available' => true,
                'data' => $this->moduleActionCounts($window),
                'definition' => 'Distinct businesses with successful tenant-scoped mutations recorded by the immutable platform audit log inside the window, matched against the documented action taxonomy. Audit metadata is redacted and stores no invoice contents, transaction details, documents, or credentials.',
            ],
            'module_opened_events' => ['available' => false, 'reason' => 'Module-view (opened) instrumentation does not exist. Enabling a module is never interpreted as a page open; recorded mutations are the only meaningful-action signal available.'],
            'unmeasured_modules' => array_keys(self::ACTION_TAXONOMY) === [] ? ['*'] : [],
            'retention_note' => 'Adoption aggregates are recomputed from audit history under the platform audit retention policy; they are not duplicated into separate analytics tables.',
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    public function customerSuccess(Request $request): array
    {
        $window = $this->window($request);
        $query = Tenant::query()
            ->whereDoesntHave('onboardingProgress', fn (Builder $p) => $p->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed'))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_id'), fn (Builder $q) => $q->whereHas('subscription', fn (Builder $s) => $s->where('plan_id', $request->string('plan_id')->toString())))
            ->when($request->filled('search'), fn (Builder $q) => $q->where('name', 'ilike', '%'.$request->string('search')->toString().'%'))
            ->orderBy('created_at', 'desc')->orderBy('id')
            ->paginate(min(100, max(10, $request->integer('per_page', 25))));
        $details = $this->attentionDetails($query->getCollection()->modelKeys());
        $query->through(fn (Tenant $tenant): array => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'registered_at' => $tenant->created_at?->toISOString(),
            'onboarding' => $details[$tenant->id]['onboarding'] ?? null,
            'open_support_tickets' => $details[$tenant->id]['open_tickets'] ?? 0,
            'subscription' => $details[$tenant->id]['subscription'] ?? null,
            'last_recorded_activity_at' => $details[$tenant->id]['last_activity'] ?? null,
            'follow_up' => $details[$tenant->id]['follow_up'] ?? null,
        ]);

        return [
            'filters' => $this->filterPayload($window),
            'summary' => [
                'businesses_by_status' => $this->statusCounts(),
                'attention_counts' => [
                    'pending_approval' => Tenant::where('status', 'pending')->count(),
                    'active_without_completed_onboarding' => Tenant::where('status', 'active')->whereDoesntHave('onboardingProgress', fn (Builder $p) => $p->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed'))->count(),
                    'open_support_tickets' => SupportTicket::whereIn('status', self::SUPPORT_OPEN_STATUSES)->count(),
                    'failed_payment_tenants_in_window' => $this->distinctFailedPaymentTenantIds($window)->count(),
                    'renewals_within_14_days' => TenantSubscription::whereIn('state', ['active', 'grace'])->whereBetween('current_period_ends_at', [now(), now()->addDays(14)])->count(),
                ],
            ],
            'signals_documentation' => 'Rows contain facts only: lifecycle state, verified onboarding completion, open support tickets, current subscription state, and the latest recorded audit activity. No heuristic health score, renewal prediction, or automatic restriction is derived from these signals; missing data displays as null.',
            'data' => $query,
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    public function retention(Request $request): array
    {
        $window = $this->window($request);
        $renewals = $this->distinctLifecycleBusinessCount($window, 'platform.tenant.subscription_renewed');
        $cancellations = $this->distinctLifecycleBusinessCount($window, 'platform.tenant.subscription_canceled');
        $changes = $this->distinctLifecycleBusinessCount($window, 'platform.tenant.subscription_changed');

        return [
            'filters' => $this->filterPayload($window),
            'definitions' => self::retentionDefinitions(),
            'events' => [
                'renewals_in_window' => ['value' => $renewals, 'available' => true, 'definition' => 'Distinct businesses with a platform.tenant.subscription_renewed audit event in the window.'],
                'cancellations_in_window' => ['value' => $cancellations, 'available' => true, 'definition' => 'Distinct businesses with a platform.tenant.subscription_canceled audit event in the window.'],
                'plan_or_cycle_changes_in_window' => ['value' => $changes, 'available' => true, 'definition' => 'Distinct businesses with a platform.tenant.subscription_changed event in the window.'],
                'involuntary_payment_failures' => ['value' => $this->distinctFailedPaymentTenantIds($window)->count(), 'available' => true, 'definition' => 'Distinct businesses with a failed platform payment record in the window. Counted per business and never merged into voluntary churn.'],
            ],
            'customer_churn_rate_percent' => $this->customerChurnRate($cancellations, $renewals),
            'trial_conversion' => ['available' => false, 'reason' => self::transitionLedgerReason()],
            'revenue_retention' => ['available' => false, 'reason' => 'No historical recurring-value ledger exists. The revenue report holds current-state MRR snapshots, which cannot be used to fabricate month-over-month retention.'],
            'cohorts_current_state' => $this->cohortRetentionSnapshot(),
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    /* ---------------- helpers ---------------- */

    private function window(Request $request): array
    {
        return $this->analytics->window($request);
    }

    private function filterPayload(array $window): array
    {
        return ['period' => $window['period'], 'granularity' => $window['granularity'], 'from' => $window['from']->toDateString(), 'to' => $window['to']->subDay()->toDateString(), 'timezone' => PlatformAnalyticsService::REPORTING_TIMEZONE];
    }

    private function statusCounts(): array
    {
        return Tenant::query()->select('status')->selectRaw('COUNT(*) AS count')->groupBy('status')->orderBy('status')->get()
            ->map(fn ($row): array => ['status' => $row->status ?? 'unknown', 'count' => (int) $row->count])->values()->all();
    }

    private function cohortDefinition(array $window): string
    {
        return "Tenants created in [{$window['from']->toDateString()} .. {$window['to']->subDay()->toDateString()}] ".PlatformAnalyticsService::REPORTING_TIMEZONE.'. Each tenant counts once; setup retries are deduplicated by tenant id.';
    }

    private function completionsInWindow(array $window): Builder
    {
        return OnboardingProgress::query()->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed')
            ->where('completed_at', '>=', $window['fromUtc'])->where('completed_at', '<', $window['toUtc']);
    }

    /** @return array{0:int,1:bool} approved tenant count and audit-coverage availability */
    private function approvedCount(array $cohortIds): array
    {
        if ($cohortIds === []) return [0, true];
        $coverage = PlatformAuditLog::query()->whereIn('auditable_id', $cohortIds)->where('auditable_type', Tenant::class)->exists();
        $count = PlatformAuditLog::query()
            ->where('action', 'platform.tenant.status_changed')
            ->whereIn('auditable_id', $cohortIds)
            ->where('auditable_type', Tenant::class)
            ->distinct()->count('auditable_id');
        return [$count, $coverage];
    }

    private function stalledTenantCount(array $cohortIds): int
    {
        if ($cohortIds === []) return 0;
        return Tenant::query()
            ->whereIn('id', $cohortIds)
            ->whereDoesntHave('onboardingProgress', fn (Builder $p) => $p->where('step_key', 'workspace_readiness')->whereNotNull('completed_at'))
            ->where(function (Builder $q): void {
                $q->whereDoesntHave('onboardingProgress')
                    ->orWhereHas('onboardingProgress', fn (Builder $p) => $p->where('updated_at', '<', now()->subDays(self::ABANDONMENT_STALL_DAYS)));
            })
            ->whereHas('memberships', fn (Builder $m) => $m->where('status', 'active')->whereHas('user', fn (Builder $u) => $u->where(function (Builder $last): void {
                $last->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(self::ABANDONMENT_STALL_DAYS));
            })))
            ->count();
    }

    /** @return array{value:?int,available:bool,coverage:int,reason?:string,definition?:string} */
    private function requiredStepCompletionRate(array $cohortIds, int $cohortSize): array
    {
        if ($cohortSize === 0) return ['value' => null, 'available' => false, 'coverage' => 0, 'reason' => 'No registered businesses in the selected cohort.'];
        $businesses = OnboardingProgress::whereIn('tenant_id', $cohortIds)->get(['tenant_id', 'step_key'])->groupBy('tenant_id');
        if ($businesses->isEmpty()) return ['value' => null, 'available' => false, 'coverage' => 0, 'reason' => 'No onboarding progress rows exist in this cohort yet; the rate stays Unknown instead of being inferred.'];
        $percentSum = 0;
        foreach ($businesses as $rows) {
            $percentSum += $rows->pluck('step_key')->intersect(self::REQUIRED_STEPS)->count() / count(self::REQUIRED_STEPS) * 100;
        }
        return [
            'value' => (int) round($percentSum / $businesses->count()),
            'available' => true,
            'coverage' => (int) round($businesses->count() / max(1, $cohortSize) * 100),
            'definition' => 'Mean completed-required-step ratio across cohort businesses holding at least one onboarding_progress record. Coverage shows how much of the cohort this rate actually describes; the remainder remains Unknown.',
        ];
    }

    private function timeToActivation($cohort, $completionRows): array
    {
        $created = $cohort->keyBy('id');
        $days = $completionRows
            ->filter(fn ($row) => isset($created[$row->tenant_id]) && $row->completed_at)
            ->map(fn ($row): int => (int) round(CarbonImmutable::instance($created[$row->tenant_id]->created_at)->diffInHours(CarbonImmutable::instance($row->completed_at)) / 24))
            ->filter(fn (int $days): bool => $days >= 0)
            ->values();
        return ['available' => $days->isNotEmpty(), 'sample_count' => $days->count(), 'average_days' => $days->isEmpty() ? null : (int) round($days->avg()), 'definition' => 'Days between tenant creation and the workspace_readiness completion timestamp, using only recorded completions.'];
    }

    /** Monthly-bucket registration and activation counts, recomputed from authoritative rows. */
    private function registrationActivationTrend(array $window): array
    {
        $granularity = $window['granularity'];
        $unit = match ($granularity) { 'daily' => 'day', 'weekly' => 'week', 'yearly' => 'year', default => 'month' };
        $format = match ($granularity) { 'daily', 'weekly' => 'Y-m-d', 'yearly' => 'Y', default => 'Y-m' };
        $registrations = Tenant::where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->selectRaw("date_trunc('{$unit}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."') AS bucket, COUNT(*) AS count")
            ->groupByRaw("date_trunc('{$unit}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."')")->get()
            ->mapWithKeys(fn ($row): array => [CarbonImmutable::parse((string) $row->bucket, PlatformAnalyticsService::REPORTING_TIMEZONE)->format($format) => (int) $row->count]);
        $activations = $this->completionsInWindow($window)
            ->selectRaw("date_trunc('{$unit}', completed_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."') AS bucket, COUNT(*) AS count")
            ->groupByRaw("date_trunc('{$unit}', completed_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."')")->get()
            ->mapWithKeys(fn ($row): array => [CarbonImmutable::parse((string) $row->bucket, PlatformAnalyticsService::REPORTING_TIMEZONE)->format($format) => (int) $row->count]);
        $points = [];
        $cursor = $window['from'];
        while ($cursor < $window['to']) {
            $key = $cursor->format($format);
            $points[] = ['bucket' => $key, 'registrations' => $registrations[$key] ?? 0, 'activations' => $activations[$key] ?? 0];
            $cursor = match ($granularity) { 'daily' => $cursor->addDay(), 'weekly' => $cursor->addWeek(), 'yearly' => $cursor->addYear(), default => $cursor->addMonthNoOverflow() };
        }
        return $points;
    }

    private function distinctLifecycleBusinessCount(array $window, string $action): int
    {
        return PlatformAuditLog::query()->where('action', $action)->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->distinct()->count('auditable_id');
    }

    private function distinctFailedPaymentTenantIds(array $window): Builder
    {
        return PlatformPayment::query()->where('status', 'failed')->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->select('tenant_id')->distinct();
    }

    private function customerChurnRate(int $cancellations, int $renewals): array
    {
        if ($cancellations === 0 && $renewals === 0) {
            return ['value' => null, 'available' => false, 'reason' => 'No confirmed churn or renewal events in the window yet; null is reported instead of a fabricated 0%.'];
        }
        if ($renewals === 0) {
            return ['value' => null, 'available' => false, 'reason' => 'Churn events exist without a confirmed retention baseline; a rate is never computed from cancellations alone.'];
        }
        $periods = $cancellations + $renewals;
        return ['value' => (int) round($cancellations / max(1, $periods) * 100), 'available' => true, 'definition' => 'Confirmed cancellations divided by confirmed lifecycle outcomes (cancellations + renewals) in the window. Customer churn only; revenue churn is unavailable without a value ledger.'];
    }

    private function cohortRetentionSnapshot(): array
    {
        $rows = DB::table('tenants')
            ->selectRaw("date_trunc('month', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."') AS bucket")
            ->selectRaw('COUNT(*) AS registered')
            ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'active' THEN id END) AS still_active")
            ->groupByRaw("date_trunc('month', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".PlatformAnalyticsService::REPORTING_TIMEZONE."')")
            ->orderBy('bucket')->limit(24)->get();
        $cohorts = $rows->map(fn ($row): array => [
            'cohort' => CarbonImmutable::parse((string) $row->bucket, PlatformAnalyticsService::REPORTING_TIMEZONE)->format('Y-m'),
            'registered' => (int) $row->registered,
            'still_active_state' => (int) $row->still_active,
            'retention_percent_current_state' => (int) $row->registered > 0 ? (int) round((int) $row->still_active / (int) $row->registered * 100) : null,
        ])->values()->all();
        return [
            'available' => $cohorts !== [],
            'cohorts' => $cohorts,
            'definition' => 'Registration cohorts with the share whose CURRENT tenant status is active. This is a current-state snapshot, not a historical retention curve; earlier states are never reconstructed from present data.',
        ];
    }

    private function attentionDetails(array $tenantIds): array
    {
        $result = [];
        $completions = OnboardingProgress::whereIn('tenant_id', $tenantIds)->where('step_key', 'workspace_readiness')->whereNotNull('completed_at')->where('status', 'completed')->get(['tenant_id', 'completed_at'])->keyBy('tenant_id');
        $tickets = SupportTicket::whereIn('tenant_id', $tenantIds)->whereIn('status', self::SUPPORT_OPEN_STATUSES)
            ->select('tenant_id', DB::raw('COUNT(*) AS open_count'))->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $subscriptions = TenantSubscription::whereIn('tenant_id', $tenantIds)->with('plan:id,code,name')->get()->keyBy('tenant_id');
        $activity = PlatformAuditLog::whereIn('tenant_id', $tenantIds)->select('tenant_id', DB::raw('MAX(created_at) AS last_activity'))->groupBy('tenant_id')->get()->keyBy('tenant_id');
        foreach ($tenantIds as $id) {
            $openTickets = (int) ($tickets->get($id)?->open_count ?? 0);
            $subscription = $subscriptions->get($id);
            $lastActivity = $activity->get($id)?->last_activity;
            $completedAt = $completions->get($id)?->completed_at;
            $followUp = [];
            if ($completedAt === null) $followUp[] = 'onboarding';
            if ($openTickets > 0) $followUp[] = 'support';
            if ($subscription && $subscription->state === 'grace') $followUp[] = 'billing';
            $result[$id] = [
                'onboarding' => ['completed' => $completedAt !== null, 'completed_at' => $completedAt?->toISOString()],
                'open_tickets' => $openTickets,
                'subscription' => $subscription ? ['state' => $subscription->state, 'plan_name' => $subscription->plan?->name, 'period_ends_at' => $subscription->current_period_ends_at?->toISOString()] : null,
                'last_activity' => $lastActivity ? CarbonImmutable::instance($lastActivity)->toISOString() : null,
                'follow_up' => $followUp === [] ? null : $followUp,
            ];
        }
        return $result;
    }

    private function moduleActionCounts(array $window): array
    {
        $counts = [];
        foreach (self::ACTION_TAXONOMY as $moduleKey => $prefixes) {
            $base = PlatformAuditLog::query()
                ->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])
                ->whereNotNull('tenant_id')
                ->where(function (Builder $q) use ($prefixes): void {
                    foreach ($prefixes as $prefix) $q->orWhere('action', 'like', $prefix.'%');
                });
            $counts[] = [
                'module_key' => $moduleKey,
                'businesses_with_actions' => (clone $base)->distinct()->count('tenant_id'),
                'recorded_events' => (int) $base->count(),
            ];
        }
        return $counts;
    }

    private function auditCoverageNote(): string
    {
        return 'Approval counts come from immutable platform audit events; coverage begins with the first recorded event. Status before coverage is never reconstructed from the current state.';
    }

    private static function transitionLedgerReason(): string
    {
        return 'The schema stores the current subscription state plus selected lifecycle audit events, not a complete immutable state-transition ledger. Conversion cannot be measured without fabricating history.';
    }

    private static function overviewDefinitions(): array
    {
        return [
            'registered_in_window' => 'Tenants created inside the reporting window.',
            'onboarding_completions_in_window' => 'workspace_readiness progress rows completed inside the window.',
            'trial_subscriptions' => 'Current subscriptions in the trialing state (snapshot, not a historical count).',
            'failed_payment_tenants_in_window' => 'Distinct businesses with a failed platform payment in the window.',
            'open_support_tickets' => 'Tickets with an open, in_progress, waiting_customer or waiting_support status.',
        ];
    }

    private static function retentionDefinitions(): array
    {
        return [
            'customer_churn' => 'Subscription cancellation confirmed by the platform.tenant.subscription_canceled audit event.',
            'revenue_churn' => 'Unavailable; requires a historical recurring-value ledger that does not exist.',
            'involuntary_churn' => 'Reported separately as failed-payment businesses; never merged into voluntary cancellation counts.',
            'cancellation_at_period_end' => 'Period-end cancellations also raise a renewed event; rates use distinct lifecycle outcomes only.',
            'reactivation' => 'A renewal event after cancellation counts as that window outcome; reactivated businesses remain in their original registration cohort.',
            'trial_cohort' => 'Undefined until a state-transition ledger exists; the current trialing snapshot is documentation only.',
            'currency_treatment' => 'Revenue-related retention is unavailable; amounts are never combined across currencies.',
        ];
    }
}
