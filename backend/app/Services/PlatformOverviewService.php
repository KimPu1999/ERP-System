<?php

namespace App\Services;

use App\Models\{Invitation, PlatformAuditLog, SecurityEvent, Tenant, TenantModule, TenantSubscription, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class PlatformOverviewService
{
    public const REPORTING_TIMEZONE = 'Asia/Yangon';

    public function summary(Request $request): array
    {
        $window = $this->window($request);
        $now = $window['now'];
        $nowUtc = $now->setTimezone('UTC');
        $growth = $this->growth($window);
        $statusCounts = $this->statusCounts();
        $subscriptionCounts = $this->subscriptionCounts();

        $pendingBusinesses = Tenant::where('status', 'pending')->count();
        $pastDueSubscriptions = TenantSubscription::whereIn('state', ['past_due', 'unpaid'])->count();
        $failedLogins = SecurityEvent::whereIn('event', ['auth.login_failed', 'auth.login_blocked'])
            ->where('created_at', '>=', $nowUtc->subDay())
            ->count();

        $activeSubscriptions = TenantSubscription::query()
            ->where(function ($query) use ($nowUtc): void {
                $query->where(function ($active) use ($nowUtc): void {
                    $active->where('state', 'active')
                        ->where(function ($period) use ($nowUtc): void {
                            $period->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', $nowUtc);
                        });
                })->orWhere(function ($trial) use ($nowUtc): void {
                    $trial->where('state', 'trialing')
                        ->where(function ($period) use ($nowUtc): void {
                            $period->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', $nowUtc);
                        });
                });
            })->count();

        $registrations = Tenant::query()
            ->with(['memberships' => fn ($query) => $query->where('status', 'active')->oldest('created_at')->with('user:id,name,email')])
            ->latest('created_at')->latest('id')->limit(8)
            ->get(['id', 'name', 'slug', 'status', 'created_at']);

        $activities = PlatformAuditLog::query()
            ->with('actor:id,name,email')
            ->latest('created_at')->latest('id')->limit(8)->get();

        return [
            'reporting_timezone' => self::REPORTING_TIMEZONE,
            'last_updated_at' => $now->toISOString(),
            'filters' => [
                'period' => $window['period'],
                'granularity' => $window['granularity'],
                'from' => $window['from']->toISOString(),
                'to' => $window['to']->toISOString(),
            ],
            'metric_definitions' => $this->metricDefinitions(),
            'total_businesses' => Tenant::count(),
            'active_businesses' => Tenant::where('status', 'active')->count(),
            'pending_approvals' => $pendingBusinesses,
            'suspended_businesses' => Tenant::where('status', 'suspended')->count(),
            'total_users' => User::count(),
            'total_platform_users' => User::where('is_platform_super_admin', true)->count(),
            'business_administrators' => $this->administratorCount(),
            'pending_invitations' => Invitation::where('status', 'pending')->where('expires_at', '>', $nowUtc)->count(),
            'enabled_modules' => TenantModule::where('enabled', true)->count(),
            'active_subscriptions' => $activeSubscriptions,
            'subscription_states' => $subscriptionCounts,
            'subscription_analytics' => [
                'available' => false,
                'reason' => 'Recurring revenue is not safely comparable across billing currencies without an exchange-rate policy.',
            ],
            'system_alerts' => [
                'total' => $pendingBusinesses + $pastDueSubscriptions + $failedLogins,
                'pending_businesses' => $pendingBusinesses,
                'past_due_subscriptions' => $pastDueSubscriptions,
                'failed_logins_last_24h' => $failedLogins,
            ],
            'monitoring' => app(MonitoringService::class)->overview(),
            'business_growth' => $growth,
            'business_status_snapshot' => $statusCounts,
            'recent_registrations' => $registrations->map(function (Tenant $tenant): array {
                $owner = $tenant->memberships->first()?->user;
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,
                    'owner' => $owner?->only(['id', 'name', 'email']),
                    'created_at' => $tenant->created_at?->toISOString(),
                ];
            })->values()->all(),
            'recent_activities' => $activities->map(fn (PlatformAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor?->only(['id', 'name', 'email']),
                'tenant_id' => $log->tenant_id,
                'resource' => $log->auditable_type ? ['type' => $log->auditable_type, 'id' => $log->auditable_id] : null,
                'result' => $this->auditResult($log->metadata ?? []),
                'created_at' => $log->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    public function analytics(Request $request): array
    {
        $window = $this->window($request);
        return [
            'period' => [
                'name' => $window['period'],
                'granularity' => $window['granularity'],
                'from' => $window['from']->toISOString(),
                'to' => $window['to']->toISOString(),
            ],
            'reporting_timezone' => self::REPORTING_TIMEZONE,
            'business_growth' => $this->growth($window),
            'businesses_by_status' => $this->statusCounts(),
            'subscriptions_by_state' => $this->subscriptionCounts(),
            'platform_users_by_status' => User::query()->where('is_platform_super_admin', true)
                ->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->orderBy('status')->get()
                ->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])->values()->all(),
        ];
    }

    private function window(Request $request): array
    {
        $now = CarbonImmutable::now(self::REPORTING_TIMEZONE);
        $period = $request->query('period', 'monthly');
        $granularity = $period === 'custom' ? $request->query('granularity', 'monthly') : $period;

        if ($period === 'custom') {
            $fromValue = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('from'), self::REPORTING_TIMEZONE);
            $toValue = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('to'), self::REPORTING_TIMEZONE);
            abort_unless($fromValue && $toValue, 422, 'A valid custom reporting date range is required.');
            $from = $fromValue->startOfDay();
            $to = $toValue->startOfDay()->addDay();
            abort_if($from >= $to, 422, 'The reporting date range is invalid.');
        } else {
            $from = match ($period) {
                'daily' => $now->startOfDay()->subDays(29),
                'weekly' => $now->startOfWeek()->subWeeks(11),
                'yearly' => $now->startOfYear()->subYears(4),
                default => $now->startOfMonth()->subMonths(5),
            };
            $to = match ($period) {
                'daily' => $now->startOfDay()->addDay(),
                'weekly' => $now->startOfWeek()->addWeek(),
                'yearly' => $now->startOfYear()->addYear(),
                default => $now->startOfMonth()->addMonth(),
            };
        }

        return compact('now', 'period', 'granularity', 'from', 'to');
    }

    private function growth(array $window): array
    {
        $granularity = $window['granularity'];
        $unit = match ($granularity) {
            'daily' => 'day',
            'weekly' => 'week',
            'yearly' => 'year',
            default => 'month',
        };
        // Laravel stores these timestamps as UTC in PostgreSQL timestamp columns
        // without a timezone. Convert UTC wall time to the reporting timezone
        // before truncating the bucket.
        $expression = "date_trunc('{$unit}', created_at AT TIME ZONE 'UTC' AT TIME ZONE '".self::REPORTING_TIMEZONE."')";
        $rows = Tenant::query()
            ->where('created_at', '>=', $window['from']->setTimezone('UTC'))
            ->where('created_at', '<', $window['to']->setTimezone('UTC'))
            ->selectRaw("{$expression} AS bucket, COUNT(*) AS count")
            ->groupByRaw($expression)->orderBy('bucket')->get();
        $counts = $rows->mapWithKeys(function ($row) use ($granularity): array {
            $bucket = CarbonImmutable::parse((string) $row->bucket, self::REPORTING_TIMEZONE);
            return [$this->bucketKey($bucket, $granularity) => (int) $row->count];
        });

        $points = [];
        $cursor = $window['from'];
        while ($cursor < $window['to']) {
            $key = $this->bucketKey($cursor, $granularity);
            $points[] = ['bucket' => $key, 'label' => $key, 'month' => $key, 'count' => (int) ($counts->get($key) ?? 0)];
            $cursor = match ($granularity) {
                'daily' => $cursor->addDay(),
                'weekly' => $cursor->addWeek(),
                'yearly' => $cursor->addYear(),
                default => $cursor->addMonth(),
            };
        }
        return $points;
    }

    private function bucketKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'daily' => $date->format('Y-m-d'),
            'weekly' => $date->startOfWeek()->format('Y-m-d'),
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    private function statusCounts(): array
    {
        return Tenant::query()->select('status')->selectRaw('COUNT(*) as count')->groupBy('status')->orderBy('status')->get()
            ->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])->values()->all();
    }

    private function subscriptionCounts(): array
    {
        return TenantSubscription::query()->select('state')->selectRaw('COUNT(*) as count')->groupBy('state')->orderBy('state')->get()
            ->map(fn ($row): array => ['state' => $row->state, 'count' => (int) $row->count])->values()->all();
    }

    private function administratorCount(): int
    {
        return (int) \App\Models\RoleAssignment::query()
            ->join('tenant_memberships', function ($join): void {
                $join->on('tenant_memberships.user_id', '=', 'role_assignments.user_id')
                    ->on('tenant_memberships.tenant_id', '=', 'role_assignments.tenant_id')
                    ->where('tenant_memberships.status', 'active');
            })->whereHas('role.permissions', fn ($query) => $query->whereIn('name', ['organization.manage', 'members.invite']))
            ->distinct('role_assignments.user_id')->count('role_assignments.user_id');
    }

    private function auditResult(array $metadata): ?string
    {
        $result = $metadata['result'] ?? $metadata['status'] ?? null;
        return is_string($result) && $result !== '' ? $result : null;
    }

    private function metricDefinitions(): array
    {
        return [
            'total_businesses' => 'All tenant records, including pending and suspended businesses; no soft-deleted tenant model is configured.',
            'active_businesses' => "Tenant records with lifecycle status 'active'.",
            'pending_approvals' => "Tenant records with lifecycle status 'pending'.",
            'total_users' => 'All user identities in the platform users table, including platform and business users.',
            'total_platform_users' => 'Verified platform administrator identities represented by the platform-admin flag.',
            'active_subscriptions' => "Active subscriptions within an open billing period plus unexpired trials; expired trials and canceled/past-due states are excluded.",
            'system_alerts' => 'Pending businesses, past-due or unpaid subscriptions, and failed login security events in the last 24 hours.',
            'business_growth' => 'New tenant registrations grouped by created_at in Asia/Yangon using an inclusive start and exclusive end boundary.',
        ];
    }
}
