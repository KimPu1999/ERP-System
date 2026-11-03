<?php

namespace App\Services;

use App\Models\{PlatformAuditLog, PlatformInvoice, PlatformPayment, RoleAssignment, SubscriptionPlan, Tenant, TenantMembership, TenantModule, TenantSubscription, User};
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PlatformAnalyticsService
{
    public const REPORTING_TIMEZONE = 'Asia/Yangon';
    private const MONEY_SCALE = 6;

    public function overview(Request $request): array
    {
        $window = $this->window($request);
        $previous = $this->previousWindow($window);
        $status = $this->businessStatusCounts();
        $subscriptions = $this->subscriptionStateCounts();
        $currentRevenue = $this->revenueTotals($window);
        $previousRevenue = $this->revenueTotals($previous);

        $businessTrend = $this->registrationTrend($window);
        $businessStatus = $status;
        $subscriptionStates = $subscriptions;
        $legacyUsers = User::where('is_platform_super_admin', true)->select('status')->selectRaw('COUNT(*) AS count')->groupBy('status')->orderBy('status')->get()->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])->values()->all();
        return [
            'period' => ['name' => $window['period'], 'granularity' => $window['granularity'], 'from' => $window['from']->toDateString(), 'to' => $window['to']->subDay()->toDateString()],
            'reporting_timezone' => self::REPORTING_TIMEZONE,
            'business_growth' => array_map(fn (array $row): array => ['bucket' => $row['bucket'], 'label' => $row['bucket'], 'month' => $row['bucket'], 'count' => $row['count']], $businessTrend),
            'businesses_by_status' => $businessStatus,
            'subscriptions_by_state' => $subscriptionStates,
            'platform_users_by_status' => $legacyUsers,
            'filters' => $this->filterPayload($window),
            'metric_definitions' => $this->metricDefinitions(),
            'kpis' => [
                'total_businesses' => $this->countMetric(Tenant::query()->count(), Tenant::query()->where('created_at', '<', $previous['toUtc'])->count()),
                'active_businesses' => $this->countMetric(Tenant::where('status', 'active')->count(), Tenant::where('status', 'active')->where('created_at', '<', $previous['toUtc'])->count()),
                'new_businesses' => $this->countMetric($this->tenantsInWindow($window)->count(), $this->tenantsInWindow($previous)->count()),
                'active_users' => $this->countMetric($this->activeUsers($window), $this->activeUsers($previous)),
                'active_subscriptions' => $this->countMetric($this->activeSubscriptions(), $this->subscriptionsInWindow($previous, ['active'])->count()),
                'trial_subscriptions' => $this->countMetric($this->subscriptionsInWindow($window, ['trialing'])->count(), $this->subscriptionsInWindow($previous, ['trialing'])->count()),
                'collected_revenue' => $this->moneyMetric(['value' => $currentRevenue['value']['collected'], 'currencies' => $currentRevenue['currencies']], ['value' => $previousRevenue['value']['collected'], 'currencies' => $previousRevenue['currencies']], $currentRevenue['available'], $currentRevenue['reason']),
                'outstanding_invoice_amount' => $this->moneyMetric(['value' => $currentRevenue['value']['outstanding'], 'currencies' => $currentRevenue['currencies']], ['value' => $previousRevenue['value']['outstanding'], 'currencies' => $previousRevenue['currencies']], $currentRevenue['available'], $currentRevenue['reason']),
            ],
            'businesses' => ['by_status' => $status, 'trend' => $businessTrend],
            'subscriptions' => ['by_state' => $subscriptions, 'plan_distribution' => $this->planDistribution()],
            'revenue' => $currentRevenue,
            'last_updated_at' => CarbonImmutable::now('UTC')->toISOString(),
        ];
    }

    public function businesses(Request $request): array
    {
        $window = $this->window($request);
        $query = Tenant::query()->withCount(['companies', 'memberships'])->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('business_type'), fn (Builder $q) => $q->whereHas('companies', fn (Builder $company) => $company->where('business_type', $request->string('business_type')->toString())))
            ->when($request->filled('plan_id'), fn (Builder $q) => $q->whereHas('subscription', fn (Builder $subscription) => $subscription->where('plan_id', $request->string('plan_id')->toString())))
            ->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc']);
        $sort = in_array($request->string('sort')->toString(), ['name', 'created_at', 'status'], true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $page = $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate($this->pageSize($request));
        $approvalDates = $this->approvalDates($page->getCollection()->pluck('id')->all());
        $page->through(fn (Tenant $tenant): array => [
            'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status,
            'registration_date' => $tenant->created_at?->toISOString(), 'approval_date' => $approvalDates[$tenant->id] ?? null,
            'companies_count' => (int) $tenant->companies_count, 'memberships_count' => (int) $tenant->memberships_count,
        ]);
        return ['filters' => $this->filterPayload($window), 'summary' => ['registered' => $this->tenantsInWindow($window)->count(), 'pending' => Tenant::where('status', 'pending')->count(), 'approved' => Tenant::where('status', 'active')->count(), 'rejected' => Tenant::where('status', 'rejected')->count(), 'suspended' => Tenant::where('status', 'suspended')->count(), 'approval_history_available' => true], 'trend' => $this->registrationTrend($window), 'data' => $page];
    }

    public function users(Request $request): array
    {
        $window = $this->window($request);
        $active = $this->activeUsers($window);
        $new = User::where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->count();
        $verified = User::whereNotNull('email_verified_at')->count();
        $roles = RoleAssignment::query()->join('roles', 'roles.id', '=', 'role_assignments.role_id')->whereNotNull('role_assignments.user_id')->select('roles.name')->selectRaw('COUNT(DISTINCT role_assignments.user_id) AS count')->groupBy('roles.name')->orderBy('roles.name')->get()->map(fn ($row): array => ['role' => $row->name, 'count' => (int) $row->count])->values()->all();
        return ['filters' => $this->filterPayload($window), 'kpis' => ['total_users' => User::count(), 'new_users' => $new, 'verified_users' => $verified, 'active_users' => $active, 'inactive_users' => User::where('status', '!=', 'active')->count()], 'role_distribution' => $roles, 'trend' => $this->userTrend($window), 'active_user_definition' => 'A unique user with status active and last_login_at inside the selected Asia/Yangon reporting window. A status flag alone is not treated as activity.', 'privacy' => 'User email addresses and private profile fields are not returned by platform analytics.' ];
    }

    public function subscriptions(Request $request): array
    {
        $window = $this->window($request);
        $states = $this->subscriptionStateCounts();
        $events = PlatformAuditLog::query()->whereIn('action', ['platform.tenant.subscription_changed', 'platform.tenant.subscription_renewed', 'platform.tenant.subscription_canceled'])->where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->select('action')->selectRaw('COUNT(*) AS count')->groupBy('action')->get()->mapWithKeys(fn ($row): array => [$row->action => (int) $row->count]);
        return ['filters' => $this->filterPayload($window), 'current_state_counts' => $states, 'new_subscriptions' => $this->subscriptionsInWindow($window)->count(), 'renewals' => (int) ($events['platform.tenant.subscription_renewed'] ?? 0), 'churned' => (int) ($events['platform.tenant.subscription_canceled'] ?? 0), 'plan_distribution' => $this->planDistribution(), 'trend' => $this->subscriptionTrend($window), 'historical_state_available' => false, 'historical_state_reason' => 'The current schema stores current subscription state and selected lifecycle audit events, but not a complete immutable state-transition ledger. Historical active-state curves are therefore unavailable.' ];
    }

    public function revenue(Request $request): array
    {
        $window = $this->window($request);
        $revenue = $this->revenueTotals($window);
        $byPlan = PlatformPayment::query()->where('platform_payments.status', 'succeeded')->whereNotNull('platform_payments.verified_at')->where('platform_payments.verified_at', '>=', $window['fromUtc'])->where('platform_payments.verified_at', '<', $window['toUtc'])->join('platform_invoices', 'platform_invoices.id', '=', 'platform_payments.invoice_id')->join('tenant_subscriptions', 'tenant_subscriptions.id', '=', 'platform_invoices.subscription_id')->join('subscription_plans', 'subscription_plans.id', '=', 'tenant_subscriptions.plan_id')->select('subscription_plans.id', 'subscription_plans.code', 'subscription_plans.name', 'platform_payments.currency_code')->selectRaw('SUM(platform_payments.amount) AS collected')->groupBy('subscription_plans.id', 'subscription_plans.code', 'subscription_plans.name', 'platform_payments.currency_code')->orderBy('subscription_plans.name')->get()->map(fn ($row): array => ['plan_id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'currency_code' => $row->currency_code, 'collected' => Decimal::normalize((string) $row->collected)])->values()->all();
        return ['filters' => $this->filterPayload($window), 'totals' => $revenue, 'by_plan' => $byPlan, 'mrr' => $this->mrr(), 'arr' => $this->arr(), 'refunds' => ['available' => false, 'reason' => 'No platform refund ledger exists in the canonical schema.'], 'money_rules' => ['collected' => 'Succeeded payments with verified_at inside the selected window; pending and failed payments are excluded.', 'invoiced' => 'Issued platform invoice totals grouped by invoice currency.', 'outstanding' => 'Invoice total minus verified paid_total, never below zero.', 'mrr' => 'Current active recurring contract value excluding trials, normalized by billing cycle and kept separate by currency.', 'arr' => 'MRR annualized by currency. Taxes, credits, proration, discounts, and refunds are not included because no authoritative ledgers exist.']];
    }

    public function usage(Request $request): array
    {
        $window = $this->window($request);
        $modules = TenantModule::query()->where('enabled', true)->select('module_key')->selectRaw('COUNT(*) AS count')->groupBy('module_key')->orderBy('module_key')->get()->map(fn ($row): array => ['module_key' => $row->module_key, 'enabled_businesses' => (int) $row->count])->values()->all();
        $businesses = Tenant::query()->leftJoin('companies', 'companies.tenant_id', '=', 'tenants.id')->leftJoin('branches', 'branches.tenant_id', '=', 'tenants.id')->leftJoin('tenant_memberships', function ($join): void { $join->on('tenant_memberships.tenant_id', '=', 'tenants.id')->where('tenant_memberships.status', '=', 'active'); })->select('tenants.id', 'tenants.name')->selectRaw('COUNT(DISTINCT companies.id) AS companies_count')->selectRaw('COUNT(DISTINCT branches.id) AS branches_count')->selectRaw('COUNT(DISTINCT tenant_memberships.user_id) AS users_count')->groupBy('tenants.id', 'tenants.name')->orderBy('tenants.name')->paginate($this->pageSize($request));
        $businesses->through(fn ($row): array => ['id' => $row->id, 'name' => $row->name, 'companies_count' => (int) $row->companies_count, 'branches_count' => (int) $row->branches_count, 'active_users_count' => (int) $row->users_count]);
        return ['filters' => $this->filterPayload($window), 'modules' => $modules, 'businesses' => $businesses, 'storage' => ['available' => false, 'reason' => 'Storage usage instrumentation is not present.'], 'api_requests' => ['available' => false, 'reason' => 'API request telemetry is not present.'], 'entitlement_usage' => ['available' => true, 'reason' => 'Current company, branch, and active membership counts are available; user, warehouse, and integration limit usage is not instrumented.']];
    }

    public function financial(Request $request): array
    {
        $window = $this->window($request); $report = $request->string('report', 'invoices')->toString();
        if ($report === 'refunds') return ['filters' => $this->filterPayload($window), 'report' => $report, 'available' => false, 'reason' => 'No platform refund ledger exists in the canonical schema.'];
        $query = $report === 'payments' ? PlatformPayment::query()->with(['tenant:id,name', 'invoice:id,number']) : PlatformInvoice::query()->with(['tenant:id,name', 'subscription.plan']);
        if ($report === 'payments') $query->where('status', 'succeeded')->whereNotNull('verified_at')->where('verified_at', '>=', $window['fromUtc'])->where('verified_at', '<', $window['toUtc']);
        else $query->where('issued_on', '>=', $window['from']->toDateString())->where('issued_on', '<', $window['to']->toDateString());
        if ($request->filled('tenant_id')) $query->where($report === 'payments' ? 'tenant_id' : 'tenant_id', $request->string('tenant_id')->toString());
        if ($request->filled('currency_code')) $query->where('currency_code', strtoupper($request->string('currency_code')->toString()));
        if ($request->filled('status') && $report !== 'payments') $query->where('status', $request->string('status')->toString());
        if ($request->filled('search')) $query->whereHas('tenant', fn (Builder $tenant) => $tenant->where('name', 'ilike', '%' . $request->string('search')->toString() . '%'));
        $sort = in_array($request->string('sort')->toString(), ['created_at', 'issued_on', 'due_on', 'total', 'amount', 'status'], true) ? $request->string('sort')->toString() : ($report === 'payments' ? 'created_at' : 'issued_on');
        $page = $query->orderBy($sort, $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')->paginate($this->pageSize($request));
        $page->through(function ($row) use ($report): array {
            if ($report === 'payments') return ['id' => $row->id, 'payment_reference' => $row->provider_payment_id, 'tenant' => $row->tenant?->name, 'invoice' => $row->invoice?->number, 'amount' => Decimal::normalize((string) $row->amount), 'currency_code' => $row->currency_code, 'verified_at' => $row->verified_at?->toISOString(), 'status' => $row->status];
            $total = Decimal::normalize((string) $row->total);
            $paid = Decimal::normalize((string) $row->paid_total);
            $outstanding = Decimal::compare($total, $paid) > 0 ? Decimal::sub($total, $paid) : '0.000000';
            return ['id' => $row->id, 'number' => $row->number, 'tenant' => $row->tenant?->name, 'plan' => $row->subscription?->plan?->name, 'status' => $row->status, 'currency_code' => $row->currency_code, 'total' => $total, 'paid_total' => $paid, 'outstanding' => $outstanding, 'issued_on' => $row->issued_on?->toDateString(), 'due_on' => $row->due_on?->toDateString()];
        });
        return ['filters' => $this->filterPayload($window), 'report' => $report, 'available' => true, 'data' => $page, 'currency_note' => 'Currency values remain labeled and are never combined across currencies.'];
    }

    public function exportQuery(string $report, array $filters): ?Builder
    {
        $window = $this->windowFromArray($filters);
        if ($report === 'invoices' || $report === 'outstanding') return PlatformInvoice::query()->with(['tenant:id,name'])->where('issued_on', '>=', $window['from']->toDateString())->where('issued_on', '<', $window['to']->toDateString())->when($report === 'outstanding', fn (Builder $q) => $q->whereColumn('total', '>', 'paid_total'))->when($filters['currency_code'] ?? null, fn (Builder $q, $currency) => $q->where('currency_code', strtoupper((string) $currency)))->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))->orderBy('id');
        if ($report === 'payments') return PlatformPayment::query()->with(['tenant:id,name', 'invoice:id,number'])->where('status', 'succeeded')->whereNotNull('verified_at')->where('verified_at', '>=', $window['fromUtc'])->where('verified_at', '<', $window['toUtc'])->when($filters['currency_code'] ?? null, fn (Builder $q, $currency) => $q->where('currency_code', strtoupper((string) $currency)))->orderBy('id');
        return null;
    }

    public function window(Request $request): array
    {
        return $this->windowFromArray(['period' => $request->input('period', 'monthly'), 'granularity' => $request->input('granularity'), 'from' => $request->input('from', $request->input('date_from')), 'to' => $request->input('to', $request->input('date_to'))]);
    }

    public function windowFromArray(array $input): array
    {
        $now = CarbonImmutable::now(self::REPORTING_TIMEZONE); $period = (string) ($input['period'] ?? 'monthly');
        if ($period === 'custom') { $from = CarbonImmutable::createFromFormat('Y-m-d', (string) ($input['from'] ?? ''), self::REPORTING_TIMEZONE); $to = CarbonImmutable::createFromFormat('Y-m-d', (string) ($input['to'] ?? ''), self::REPORTING_TIMEZONE); abort_unless($from && $to, 422, 'A valid custom reporting date range is required.'); $from = $from->startOfDay(); $to = $to->startOfDay()->addDay(); }
        else { [$from, $to] = match ($period) { 'today' => [$now->startOfDay(), $now->startOfDay()->addDay()], 'yesterday' => [$now->startOfDay()->subDay(), $now->startOfDay()], 'last_7_days' => [$now->startOfDay()->subDays(6), $now->startOfDay()->addDay()], 'last_30_days' => [$now->startOfDay()->subDays(29), $now->startOfDay()->addDay()], 'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth()], 'this_year' => [$now->startOfYear(), $now->startOfYear()->addYear()], 'daily' => [$now->startOfDay()->subDays(29), $now->startOfDay()->addDay()], 'weekly' => [$now->startOfWeek()->subWeeks(11), $now->startOfWeek()->addWeek()], 'yearly' => [$now->startOfYear()->subYears(4), $now->startOfYear()->addYear()], default => [$now->startOfMonth()->subMonths(5), $now->startOfMonth()->addMonth()] }; }
        abort_if($from >= $to, 422, 'The reporting date range is invalid.');
        $granularity = (string) ($input['granularity'] ?? match ($period) { 'today', 'yesterday', 'last_7_days', 'last_30_days', 'daily' => 'daily', 'weekly' => 'weekly', 'yearly', 'this_year' => 'monthly', default => 'monthly' });
        return ['period' => $period, 'granularity' => in_array($granularity, ['daily', 'weekly', 'monthly', 'yearly'], true) ? $granularity : 'monthly', 'from' => $from, 'to' => $to, 'fromUtc' => $from->setTimezone('UTC'), 'toUtc' => $to->setTimezone('UTC')];
    }

    private function previousWindow(array $window): array
    { $seconds = $window['to']->diffInSeconds($window['from']); return [...$window, 'from' => $window['from']->subSeconds($seconds), 'to' => $window['from'], 'fromUtc' => $window['fromUtc']->subSeconds($seconds), 'toUtc' => $window['fromUtc']]; }
    private function filterPayload(array $window): array { return ['period' => $window['period'], 'granularity' => $window['granularity'], 'from' => $window['from']->toDateString(), 'to' => $window['to']->subDay()->toDateString(), 'from_utc' => $window['fromUtc']->toISOString(), 'to_utc_exclusive' => $window['toUtc']->toISOString(), 'timezone' => self::REPORTING_TIMEZONE]; }
    private function pageSize(Request $request): int { return min(100, max(10, $request->integer('per_page', 25))); }
    private function tenantsInWindow(array $window): Builder { return Tenant::where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc']); }
    private function activeUsers(array $window): int { return User::where('status', 'active')->whereNotNull('last_login_at')->where('last_login_at', '>=', $window['fromUtc'])->where('last_login_at', '<', $window['toUtc'])->count(); }
    private function activeSubscriptions(): int { return TenantSubscription::whereIn('state', ['active', 'trialing'])->where(function ($q): void { $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', now()); })->count(); }
    private function subscriptionsInWindow(array $window, array $states = []): Builder { return TenantSubscription::where('created_at', '>=', $window['fromUtc'])->where('created_at', '<', $window['toUtc'])->when($states !== [], fn (Builder $q) => $q->whereIn('state', $states)); }
    private function countMetric(int $current, int $previous): array { return ['value' => $current, 'previous_value' => $previous, 'change_percent' => $previous === 0 ? null : round((($current - $previous) / $previous) * 100, 2), 'available' => true, 'reason' => null]; }
    private function moneyMetric(array $current, array $previous, bool $available, ?string $reason): array { $change = null; if ($available && $previous['value'] !== null && Decimal::compare((string) $previous['value'], '0') !== 0) $change = Decimal::div(Decimal::mul(Decimal::sub((string) $current['value'], (string) $previous['value']), '100', 8), (string) $previous['value'], 2); return ['value' => $current['value'], 'previous_value' => $previous['value'], 'change_percent' => $change, 'available' => $available, 'reason' => $reason, 'currencies' => $current['currencies']]; }
    private function revenueTotals(array $window): array
    { $invoiced = PlatformInvoice::where('issued_on', '>=', $window['from']->toDateString())->where('issued_on', '<', $window['to']->toDateString())->select('currency_code')->selectRaw('SUM(total) AS amount')->groupBy('currency_code')->get(); $collected = PlatformPayment::where('status', 'succeeded')->whereNotNull('verified_at')->where('verified_at', '>=', $window['fromUtc'])->where('verified_at', '<', $window['toUtc'])->select('currency_code')->selectRaw('SUM(amount) AS amount')->groupBy('currency_code')->get(); $outstanding = PlatformInvoice::where('issued_on', '>=', $window['from']->toDateString())->where('issued_on', '<', $window['to']->toDateString())->select('currency_code')->selectRaw('SUM(GREATEST(total - paid_total, 0)) AS amount')->groupBy('currency_code')->get(); $currencies = collect($invoiced->pluck('currency_code')->merge($collected->pluck('currency_code'))->merge($outstanding->pluck('currency_code'))->unique()->sort()->values()); $map = fn ($rows): array => $rows->mapWithKeys(fn ($row): array => [$row->currency_code => Decimal::normalize((string) $row->amount)])->all(); $invoicedMap = $map($invoiced); $collectedMap = $map($collected); $outstandingMap = $map($outstanding); $single = $currencies->count() === 1 ? $currencies->first() : null; return ['available' => $currencies->count() <= 1, 'reason' => $currencies->count() > 1 ? 'Values are separated by currency because no exchange-rate policy is configured.' : null, 'currency_code' => $single, 'currencies' => $currencies->map(fn ($currency): array => ['currency_code' => $currency, 'invoiced' => $invoicedMap[$currency] ?? '0.000000', 'collected' => $collectedMap[$currency] ?? '0.000000', 'outstanding' => $outstandingMap[$currency] ?? '0.000000'])->all(), 'value' => ['invoiced' => $single ? ($invoicedMap[$single] ?? '0.000000') : null, 'collected' => $single ? ($collectedMap[$single] ?? '0.000000') : null, 'outstanding' => $single ? ($outstandingMap[$single] ?? '0.000000') : null]]; }
    private function businessStatusCounts(): array { return Tenant::select('status')->selectRaw('COUNT(*) AS count')->groupBy('status')->orderBy('status')->get()->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])->values()->all(); }
    private function subscriptionStateCounts(): array { return TenantSubscription::select('state')->selectRaw('COUNT(*) AS count')->groupBy('state')->orderBy('state')->get()->map(fn ($row): array => ['state' => $row->state, 'count' => (int) $row->count])->values()->all(); }
    private function planDistribution(): array { return TenantSubscription::join('subscription_plans', 'subscription_plans.id', '=', 'tenant_subscriptions.plan_id')->select('subscription_plans.id', 'subscription_plans.code', 'subscription_plans.name')->selectRaw('COUNT(*) AS count')->groupBy('subscription_plans.id', 'subscription_plans.code', 'subscription_plans.name')->orderBy('subscription_plans.name')->get()->map(fn ($row): array => ['plan_id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'count' => (int) $row->count])->values()->all(); }
    private function registrationTrend(array $window): array { return $this->timeTrend(Tenant::query(), 'created_at', $window); }
    private function userTrend(array $window): array { return $this->timeTrend(User::query(), 'created_at', $window); }
    private function subscriptionTrend(array $window): array { return $this->timeTrend(TenantSubscription::query(), 'created_at', $window); }
    private function timeTrend(Builder $query, string $column, array $window): array { $granularity = $window['granularity']; $unit = match ($granularity) { 'daily' => 'day', 'weekly' => 'week', 'yearly' => 'year', default => 'month' }; $expression = "date_trunc('{$unit}', {$column} AT TIME ZONE 'UTC' AT TIME ZONE '" . self::REPORTING_TIMEZONE . "')"; $format = match ($granularity) { 'daily', 'weekly' => 'Y-m-d', 'yearly' => 'Y', default => 'Y-m' }; $rows = $query->where($column, '>=', $window['fromUtc'])->where($column, '<', $window['toUtc'])->selectRaw("{$expression} AS bucket, COUNT(*) AS count")->groupByRaw($expression)->orderBy('bucket')->get()->mapWithKeys(fn ($row): array => [CarbonImmutable::parse((string) $row->bucket, self::REPORTING_TIMEZONE)->format($format) => (int) $row->count]); $points = []; $cursor = $granularity === 'weekly' ? $window['from']->startOfWeek() : $window['from']; while ($cursor < $window['to']) { $key = $cursor->format($format); $points[] = ['bucket' => $key, 'count' => (int) ($rows[$key] ?? 0)]; $cursor = match ($granularity) { 'daily' => $cursor->addDay(), 'weekly' => $cursor->addWeek(), 'yearly' => $cursor->addYear(), default => $cursor->addMonth() }; } return $points; }
    private function approvalDates(array $tenantIds): array { if ($tenantIds === []) return []; return PlatformAuditLog::whereIn('auditable_id', $tenantIds)->where('auditable_type', Tenant::class)->where('action', 'platform.tenant.status_changed')->whereJsonContains('metadata->to', 'active')->select('auditable_id', 'created_at')->orderBy('created_at')->get()->groupBy('auditable_id')->map(fn ($logs) => $logs->first()->created_at?->toISOString())->all(); }
    private function mrr(): array { return $this->recurringValue('monthly'); }
    private function arr(): array { $mrr = $this->recurringValue('monthly'); return [...$mrr, 'currencies' => collect($mrr['currencies'])->map(fn ($row): array => [...$row, 'value' => Decimal::mul($row['value'], '12')])->all()]; }
    private function recurringValue(string $basis): array { $rows = TenantSubscription::query()->join('subscription_plans', 'subscription_plans.id', '=', 'tenant_subscriptions.plan_id')->where('tenant_subscriptions.state', 'active')->select('tenant_subscriptions.currency_code')->selectRaw("SUM(CASE WHEN tenant_subscriptions.billing_cycle = 'annual' THEN tenant_subscriptions.price_snapshot / 12 ELSE tenant_subscriptions.price_snapshot END) AS amount")->groupBy('tenant_subscriptions.currency_code')->get(); $values = $rows->map(fn ($row): array => ['currency_code' => $row->currency_code, 'value' => Decimal::normalize((string) $row->amount)])->all(); return ['available' => count($values) <= 1, 'reason' => count($values) > 1 ? 'Recurring values are separated by currency.' : null, 'currency_code' => count($values) === 1 ? $values[0]['currency_code'] : null, 'value' => count($values) === 1 ? $values[0]['value'] : null, 'currencies' => $values, 'basis' => 'Active subscriptions only; trials excluded; annual plans divided by 12.']; }
    private function metricDefinitions(): array { return ['total_businesses' => 'All tenant records, all time.', 'active_businesses' => "Current tenants with status 'active'; this is not a historical status reconstruction.", 'new_businesses' => 'Tenant.created_at inside the selected Asia/Yangon period.', 'active_users' => 'Distinct users with status active and last_login_at inside the selected period.', 'active_subscriptions' => 'Current active/trialing subscriptions with an open current period.', 'collected_revenue' => 'Verified succeeded platform payments by verified_at; failed and pending payments excluded.', 'outstanding_invoice_amount' => 'Issued platform invoice total minus paid_total, floored at zero.', 'timezone' => self::REPORTING_TIMEZONE, 'boundaries' => 'Inclusive start and exclusive end.' ]; }
}
