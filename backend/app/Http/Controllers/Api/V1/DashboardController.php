<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, Branch, Company, SalesInvoice, SalesOrder};
use App\Services\AuthorizationService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController
{
    public function show(Request $request, AuthorizationService $authorization): JsonResponse
    {
        // ResolveTenant has already validated this header and set the database tenant context.
        // Read the request value here as the stable request-scoped identifier.
        $tenantId = (string) $request->header('X-Tenant-Id');
        $filters = $request->validate([
            'period' => ['nullable', 'in:today,last_7_days,last_30_days,this_month,this_year,custom'],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:from'],
            'company_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
        ]);
        $period = $filters['period'] ?? 'last_30_days';
        [$from, $to] = $this->range($period, $filters);
        $companyId = $filters['company_id'] ?? null;
        $branchId = $filters['branch_id'] ?? null;

        abort_unless($authorization->allows($request->user(), $tenantId, 'reports.view', $companyId, $branchId), 403);
        if ($companyId) abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->exists(), 404);
        if ($branchId) abort_unless($companyId && Branch::where('tenant_id', $tenantId)->whereKey($branchId)->where('company_id', $companyId)->exists(), 404);

        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($from->diffInDays($to))->startOfDay();
        $currency = AccountingCurrency::where('tenant_id', $tenantId)->where('is_base', true)->first(['code', 'decimal_places']);

        $revenue = $this->invoiceTotal($tenantId, $companyId, $branchId, $from, $to);
        $previousRevenue = $this->invoiceTotal($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $purchases = $this->purchaseTotal($tenantId, $companyId, $branchId, $from, $to);
        $previousPurchases = $this->purchaseTotal($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $grossProfit = $this->grossProfit($tenantId, $companyId, $branchId, $from, $to);
        $previousGrossProfit = $this->grossProfit($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $inventoryValue = $this->inventoryValue($tenantId, $companyId, $branchId);
        $lowStock = $this->lowStockCount($tenantId, $companyId, $branchId);

        return response()->json(['data' => [
            'filters' => ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'company_id' => $companyId, 'branch_id' => $branchId],
            'currency' => ['code' => $currency?->code, 'decimal_places' => $currency?->decimal_places, 'configured' => $currency !== null],
            'kpis' => [
                'revenue' => $this->kpi($revenue, $previousRevenue),
                'sales_orders' => $this->kpi((string) $this->salesOrders($tenantId, $companyId, $branchId, $from, $to), (string) $this->salesOrders($tenantId, $companyId, $branchId, $previousFrom, $previousTo)),
                'pending_invoices' => ['value' => (string) $this->pendingInvoices($tenantId, $companyId, $branchId), 'previous_value' => null, 'change_percent' => null],
                'pending_deliveries' => ['value' => (string) $this->pendingDeliveries($tenantId, $companyId, $branchId), 'previous_value' => null, 'change_percent' => null],
                'gross_profit' => $this->kpi($grossProfit, $previousGrossProfit),
                'purchases' => $this->kpi($purchases, $previousPurchases),
                'inventory_value' => ['value' => $inventoryValue, 'previous_value' => null, 'change_percent' => null],
                'low_stock_products' => ['value' => (string) $lowStock, 'previous_value' => null, 'change_percent' => null],
            ],
            'charts' => [
                'revenue_expenses' => $this->dailySeries($tenantId, $companyId, $branchId, $from, $to),
                'sales_trend' => $this->monthlySeries($tenantId, $companyId, $branchId, $from, $to),
                'sales_by_category' => $this->salesByCategory($tenantId, $companyId, $branchId, $from, $to),
            ],
            'recent_sales' => $this->recentSales($tenantId, $companyId, $branchId),
            'top_products' => $this->topProducts($tenantId, $companyId, $branchId, $from, $to),
                'top_products_by_volume' => $this->topProducts($tenantId, $companyId, $branchId, $from, $to, 'units_sold'),
            'inventory_alerts' => $this->inventoryAlerts($tenantId, $companyId, $branchId),
            'pending_approvals' => $this->pendingApprovals($tenantId, $companyId),
        ]]);
    }

    private function range(string $period, array $filters): array
    {
        $today = CarbonImmutable::today();
        return match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'last_7_days' => [$today->subDays(6)->startOfDay(), $today->endOfDay()],
            'this_month' => [$today->startOfMonth(), $today->endOfDay()],
            'this_year' => [$today->startOfYear(), $today->endOfDay()],
            'custom' => [CarbonImmutable::parse($filters['from'])->startOfDay(), CarbonImmutable::parse($filters['to'])->endOfDay()],
            default => [$today->subDays(29)->startOfDay(), $today->endOfDay()],
        };
    }

    private function invoiceTotal(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $query = SalesInvoice::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]);
        return (string) ($this->scope($query, $companyId, $branchId)->sum(DB::raw('COALESCE(base_total, total)')) ?: '0');
    }

    private function purchaseTotal(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $query = DB::table('purchase_orders as po')->where('po.tenant_id', $tenantId)->whereNotIn('po.state', ['draft', 'cancelled', 'rejected'])->whereBetween('po.created_at', [$from, $to]);
        $value = $this->scope($query, $companyId, $branchId, 'po')->sum(DB::raw('COALESCE(po.base_total, (SELECT COALESCE(SUM(poi.ordered_quantity * poi.unit_price), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id))'));
        return (string) ($value ?: '0');
    }

    private function grossProfit(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $query = DB::table('sales_invoice_items as sii')->join('sales_invoices as si', 'si.id', '=', 'sii.invoice_id')->join('product_variants as pv', 'pv.id', '=', 'sii.variant_id')->where('si.tenant_id', $tenantId)->whereNotIn('si.state', ['draft', 'cancelled', 'void'])->whereBetween('si.created_at', [$from, $to]);
        $query = $this->scope($query, $companyId, $branchId, 'si');
        $value = $query->sum(DB::raw('(sii.line_total - (sii.quantity * pv.cost)) * COALESCE(si.exchange_rate, 1)'));
        return (string) ($value ?: '0');
    }

    private function salesOrders(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]), $companyId, $branchId)->count();
    }

    private function pendingInvoices(string $tenantId, ?string $companyId, ?string $branchId): int
    {
        return $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereIn('state', ['issued', 'partially_paid']), $companyId, $branchId)->count();
    }

    private function pendingDeliveries(string $tenantId, ?string $companyId, ?string $branchId): int
    {
        return $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereIn('state', ['approved', 'partially_fulfilled']), $companyId, $branchId)->count();
    }

    private function inventoryValue(string $tenantId, ?string $companyId, ?string $branchId): string
    {
        $query = DB::table('inventory_balances as ib')->join('product_variants as pv', 'pv.id', '=', 'ib.variant_id')->join('warehouse_locations as wl', 'wl.id', '=', 'ib.location_id')->join('warehouses as w', 'w.id', '=', 'wl.warehouse_id')->where('ib.tenant_id', $tenantId)->when($companyId, fn ($q) => $q->where('w.company_id', $companyId))->when($branchId, fn ($q) => $q->where('w.branch_id', $branchId));
        return (string) ($query->sum(DB::raw('ib.quantity * pv.cost')) ?: '0');
    }

    private function lowStockCount(string $tenantId, ?string $companyId, ?string $branchId): int
    {
        return DB::table('inventory_alerts as ia')->join('warehouse_locations as wl', 'wl.id', '=', 'ia.location_id')->join('warehouses as w', 'w.id', '=', 'wl.warehouse_id')->where('ia.tenant_id', $tenantId)->whereNull('ia.resolved_at')->when($companyId, fn ($q) => $q->where('w.company_id', $companyId))->when($branchId, fn ($q) => $q->where('w.branch_id', $branchId))->distinct('ia.variant_id')->count('ia.variant_id');
    }

    private function dailySeries(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $revenue = $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]), $companyId, $branchId)->selectRaw('DATE(created_at) as day, SUM(COALESCE(base_total, total)) as value')->groupBy('day')->pluck('value', 'day');
        $expenses = $this->scope(DB::table('purchase_orders as po')->where('po.tenant_id', $tenantId)->whereNotIn('po.state', ['draft', 'cancelled', 'rejected'])->whereBetween('po.created_at', [$from, $to]), $companyId, $branchId, 'po')->selectRaw('DATE(po.created_at) as day, SUM(COALESCE(po.base_total, (SELECT COALESCE(SUM(poi.ordered_quantity * poi.unit_price), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id))) as value')->groupBy('day')->pluck('value', 'day');
        $result = [];
        for ($date = $from->startOfDay(); $date->lte($to); $date = $date->addDay()) $result[] = ['label' => $date->format('M j'), 'revenue' => (string) ($revenue[$date->toDateString()] ?? '0'), 'expenses' => (string) ($expenses[$date->toDateString()] ?? '0')];
        return $this->sampleSeries($result, 14);
    }

    private function monthlySeries(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]), $companyId, $branchId)->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, SUM(COALESCE(base_total, total)) as value")->groupBy('month')->pluck('value', 'month');
        $result = [];
        for ($date = $from->startOfMonth(); $date->lte($to); $date = $date->addMonth()) { $key = $date->format('Y-m'); $result[] = ['label' => $date->format('M Y'), 'value' => (string) ($rows[$key] ?? '0')]; }
        return $this->sampleSeries($result, 12);
    }

    private function salesByCategory(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = DB::table('sales_invoice_items as sii')->join('sales_invoices as si', 'si.id', '=', 'sii.invoice_id')->join('product_variants as pv', 'pv.id', '=', 'sii.variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')->leftJoin('product_categories as pc', 'pc.id', '=', 'p.category_id')->where('si.tenant_id', $tenantId)->whereNotIn('si.state', ['draft', 'cancelled', 'void'])->whereBetween('si.created_at', [$from, $to]);
        return $this->scope($query, $companyId, $branchId, 'si')->selectRaw("COALESCE(pc.name, 'Uncategorized') as label, SUM(sii.line_total * COALESCE(si.exchange_rate, 1)) as value")->groupBy('label')->orderByDesc('value')->limit(6)->get()->map(fn ($row) => ['label' => $row->label, 'value' => (string) $row->value])->all();
    }

    private function recentSales(string $tenantId, ?string $companyId, ?string $branchId): array
    {
        return $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->with('customer')->latest()->limit(8), $companyId, $branchId)->get()->map(fn (SalesOrder $order) => ['id' => $order->id, 'number' => $order->number, 'customer' => $order->customer?->name ?? '—', 'date' => $order->created_at?->toDateString(), 'amount' => (string) ($order->base_total ?? $order->total), 'status' => $order->state])->all();
    }

    private function topProducts(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to, string $sort = 'revenue'): array
    {
        $query = DB::table('sales_invoice_items as sii')->join('sales_invoices as si', 'si.id', '=', 'sii.invoice_id')->join('product_variants as pv', 'pv.id', '=', 'sii.variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')->where('si.tenant_id', $tenantId)->whereNotIn('si.state', ['draft', 'cancelled', 'void'])->whereBetween('si.created_at', [$from, $to]);
        return $this->scope($query, $companyId, $branchId, 'si')->select('p.name as product', 'pv.sku')->selectRaw('SUM(sii.quantity) as units_sold, SUM(sii.line_total * COALESCE(si.exchange_rate, 1)) as revenue')->groupBy('p.name', 'pv.sku')->orderByDesc($sort === 'units_sold' ? 'units_sold' : 'revenue')->limit(8)->get()->map(fn ($row) => ['product' => $row->product, 'sku' => $row->sku, 'units_sold' => (string) $row->units_sold, 'revenue' => (string) $row->revenue])->all();
    }

    private function scope($query, ?string $companyId, ?string $branchId, string $prefix = '')
    {
        $companyColumn = $prefix !== '' ? $prefix.'.company_id' : 'company_id';
        $branchColumn = $prefix !== '' ? $prefix.'.branch_id' : 'branch_id';
        return $query->when($companyId, fn ($builder) => $builder->where($companyColumn, $companyId))->when($branchId, fn ($builder) => $builder->where($branchColumn, $branchId));
    }

    private function inventoryAlerts(string $tenantId, ?string $companyId, ?string $branchId): array
    {
        return DB::table('inventory_alerts as ia')
            ->join('product_variants as pv', 'pv.id', '=', 'ia.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouse_locations as wl', 'wl.id', '=', 'ia.location_id')
            ->join('warehouses as w', 'w.id', '=', 'wl.warehouse_id')
            ->leftJoin('inventory_balances as ib', function ($join): void {
                $join->on('ib.variant_id', '=', 'ia.variant_id')->on('ib.location_id', '=', 'ia.location_id')->whereColumn('ib.tenant_id', 'ia.tenant_id');
            })
            ->leftJoin('reorder_rules as rr', function ($join): void {
                $join->on('rr.variant_id', '=', 'ia.variant_id')->on('rr.location_id', '=', 'ia.location_id')->whereColumn('rr.tenant_id', 'ia.tenant_id');
            })
            ->where('ia.tenant_id', $tenantId)
            ->whereNull('ia.resolved_at')
            ->when($companyId, fn ($q) => $q->where('w.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('w.branch_id', $branchId))
            ->orderByDesc('ia.created_at')
            ->limit(8)
            ->get(['ia.id', 'p.name as product', 'w.name as warehouse', 'ia.type as status', 'rr.reorder_point as reorder_level', DB::raw('COALESCE(ib.quantity, 0) - COALESCE(ib.reserved_quantity, 0) as available_stock')])
            ->map(fn ($row) => ['id' => $row->id, 'product' => $row->product, 'warehouse' => $row->warehouse, 'available_stock' => (string) $row->available_stock, 'reorder_level' => (string) ($row->reorder_level ?? '0'), 'status' => $row->status])
            ->all();
    }

    private function pendingApprovals(string $tenantId, ?string $companyId): array
    {
        return DB::table('approval_requests as ar')->join('users as u', 'u.id', '=', 'ar.requester_id')->where('ar.tenant_id', $tenantId)->where('ar.state', 'pending')->when($companyId, fn ($q) => $q->where('ar.company_id', $companyId))->orderByDesc('ar.created_at')->limit(8)->get(['ar.id', 'ar.document_type', 'ar.document_id', 'ar.amount', 'ar.created_at', 'ar.state', 'u.name as requested_by'])->map(fn ($row) => ['id' => $row->id, 'document_type' => $row->document_type, 'reference' => $row->document_id, 'requested_by' => $row->requested_by, 'date' => CarbonImmutable::parse($row->created_at)->toDateString(), 'amount' => (string) $row->amount, 'status' => $row->state])->all();
    }

    private function kpi(string $value, string $previous): array
    {
        $change = Decimal::compare($previous, '0') === 0 ? null : Decimal::mul(Decimal::div(Decimal::sub($value, $previous), $previous, 6), '100', 2);
        return ['value' => $value, 'previous_value' => $previous, 'change_percent' => $change];
    }

    private function sampleSeries(array $series, int $max): array
    {
        if (count($series) <= $max) return $series;
        $step = (int) ceil(count($series) / $max);
        return array_values(array_filter($series, fn ($item, $index) => $index % $step === 0, ARRAY_FILTER_USE_BOTH));
    }
}
