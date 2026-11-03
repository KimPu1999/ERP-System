<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Tenant, TenantSubscription, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class PlatformAnalyticsController
{
    public function __invoke(): JsonResponse
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $months = collect(range(5, 0, -1))->mapWithKeys(function (int $offset) use ($start): array {
            $month = $start->addMonths($offset)->format('Y-m');
            return [$month => 0];
        });

        Tenant::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->each(function (Tenant $tenant) use (&$months): void {
                $month = $tenant->created_at?->format('Y-m');
                if ($month !== null && $months->has($month)) $months = $months->put($month, $months->get($month) + 1);
            });

        $statusCounts = Tenant::query()
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])
            ->values();

        $subscriptionCounts = TenantSubscription::query()
            ->select('state')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('state')
            ->orderBy('state')
            ->get()
            ->map(fn ($row): array => ['state' => $row->state, 'count' => (int) $row->count])
            ->values();

        $platformUserCounts = User::query()
            ->where('is_platform_super_admin', true)
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => ['status' => $row->status, 'count' => (int) $row->count])
            ->values();

        return response()->json(['data' => [
            'period' => ['from' => $start->toISOString(), 'to' => CarbonImmutable::now()->toISOString()],
            'business_growth' => $months->map(fn (int $count, string $month): array => ['month' => $month, 'count' => $count])->values(),
            'businesses_by_status' => $statusCounts,
            'subscriptions_by_state' => $subscriptionCounts,
            'platform_users_by_status' => $platformUserCounts,
        ]]);
    }
}
