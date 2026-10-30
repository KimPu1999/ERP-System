<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, Branch, Company, SalesInvoice, SalesOrder};
use App\Services\AuthorizationService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DashboardController
{
    public function show(Request $request, AuthorizationService $authorization): JsonResponse
    {
        // ResolveTenant has already validated this header and set the database tenant context.
        // Read the request value here as the stable request-scoped identifier.
        $tenantId = (string) $request->header('X-Tenant-Id');
        $filters = $request->validate([
            'period' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month,this_quarter,this_year,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'company_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
        ]);
        $period = $filters['period'] ?? 'last_30_days';
        $dateFrom = $filters['date_from'] ?? $filters['from'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to'] ?? null;
        if ($period === 'custom' && (! $dateFrom || ! $dateTo)) throw ValidationException::withMessages(['date_from' => ['Both date_from and date_to are required for a custom period.']]);
        if ($dateFrom && $dateTo && CarbonImmutable::parse($dateFrom)->gt(CarbonImmutable::parse($dateTo))) throw ValidationException::withMessages(['date_to' => ['date_to must be on or after date_from.']]);
        $companyId = $filters['company_id'] ?? null;
        $branchId = $filters['branch_id'] ?? null;

        abort_unless($authorization->allows($request->user(), $tenantId, 'reports.view', $companyId, $branchId), 403);
        if ($companyId) abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->exists(), 404);
        if ($branchId) abort_unless($companyId && Branch::where('tenant_id', $tenantId)->whereKey($branchId)->where('company_id', $companyId)->exists(), 404);
        $timezone = $companyId ? (string) Company::where('tenant_id', $tenantId)->whereKey($companyId)->value('timezone') : (string) config('app.timezone', 'UTC');
        if (! in_array($timezone, timezone_identifiers_list(), true)) $timezone = (string) config('app.timezone', 'UTC');
        [$from, $to] = $this->range($period, ['from' => $dateFrom, 'to' => $dateTo], $timezone);

        $previousTo = $from->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($from->diffInDays($to))->startOfDay();
        $currency = $companyId
            ? AccountingCurrency::query()
                ->where('tenant_id', $tenantId)
                ->where('code', Company::where('tenant_id', $tenantId)->whereKey($companyId)->value('base_currency'))
                ->first(['code', 'decimal_places'])
            : AccountingCurrency::where('tenant_id', $tenantId)->where('is_base', true)->first(['code', 'decimal_places']);
        $financialAvailable = $currency !== null;

        $revenue = $this->invoiceTotal($tenantId, $companyId, $branchId, $from, $to);
        $previousRevenue = $this->invoiceTotal($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $purchases = $this->purchaseTotal($tenantId, $companyId, $branchId, $from, $to);
        $previousPurchases = $this->purchaseTotal($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $grossProfit = $this->grossProfit($tenantId, $companyId, $branchId, $from, $to);
        $previousGrossProfit = $this->grossProfit($tenantId, $companyId, $branchId, $previousFrom, $previousTo);
        $inventoryValue = $this->inventoryValue($tenantId, $companyId, $branchId);
        $lowStock = $this->lowStockCount($tenantId, $companyId, $branchId);

        return response()->json(['data' => [
            'filters' => ['period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'company_id' => $companyId, 'branch_id' => $branchId, 'timezone' => $timezone],
            'currency' => ['code' => $currency?->code, 'decimal_places' => $currency?->decimal_places, 'configured' => $currency !== null],
            'kpis' => [
                'revenue' => $this->kpi($revenue, $previousRevenue, $financialAvailable, $financialAvailable ? null : 'Base accounting currency is not configured.'),
                'sales_orders' => $this->kpi((string) $this->salesOrders($tenantId, $companyId, $branchId, $from, $to), (string) $this->salesOrders($tenantId, $companyId, $branchId, $previousFrom, $previousTo)),
                'pending_invoices' => $this->metric((string) $this->pendingInvoices($tenantId, $companyId, $branchId)),
                'pending_deliveries' => $this->metric((string) $this->pendingDeliveries($tenantId, $companyId, $branchId)),
                'gross_profit' => $this->kpi($grossProfit, $previousGrossProfit, $financialAvailable, $financialAvailable ? null : 'Base accounting currency is not configured.'),
                'purchases' => $this->kpi($purchases, $previousPurchases, $financialAvailable, $financialAvailable ? null : 'Base accounting currency is not configured.'),
                'inventory_value' => $this->metric($inventoryValue, $financialAvailable, $financialAvailable ? null : 'Base accounting currency is not configured.'),
                'low_stock_products' => $this->metric((string) $lowStock),
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

    public function salesDashboard(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        $tenantId = $context['tenant_id']; $companyId = $context['company_id']; $branchId = $context['branch_id']; $from = $context['from']; $to = $context['to']; $currency = $context['currency'];
        $eligibleInvoiceStates = ['issued', 'partially_paid', 'paid'];
        $eligibleOrderStates = ['pending_approval', 'approved', 'processing', 'partially_fulfilled', 'fulfilled', 'delivered'];
        $orderQuery = $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereIn('state', $eligibleOrderStates)->whereBetween('created_at', [$from, $to]), $companyId, $branchId);
        $invoiceQuery = $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereIn('state', $eligibleInvoiceStates)->whereBetween('created_at', [$from, $to]), $companyId, $branchId);
        $revenue = $currency ? $this->invoiceTotal($tenantId, $companyId, $branchId, $from, $to) : '0';
        $previousRevenue = $currency ? $this->invoiceTotal($tenantId, $companyId, $branchId, $context['previous_from'], $context['previous_to']) : '0';
        $paymentQuery = DB::table('payment_allocations as pa')->join('payments_received as pr', 'pr.id', '=', 'pa.payment_id')->join('sales_invoices as si', 'si.id', '=', 'pa.invoice_id')->where('pa.tenant_id', $tenantId)->where('pr.tenant_id', $tenantId)->where('si.tenant_id', $tenantId)->where('pr.state', 'verified')->whereBetween(DB::raw('COALESCE(pr.verified_at, pr.created_at)'), [$from, $to])->when($companyId, fn ($q) => $q->where('si.company_id', $companyId))->when($branchId, fn ($q) => $q->where('si.branch_id', $branchId));
        $payments = (string) ($paymentQuery->sum('pa.amount') ?: '0');
        $ar = $this->receivablesSummary($tenantId, $companyId, $branchId, $to);
        $statusRows = (clone $orderQuery)->select('state')->selectRaw('COUNT(*) as count')->groupBy('state')->pluck('count', 'state');
        $customers = (clone $invoiceQuery)->whereNotNull('customer_id')->distinct()->count('customer_id');
        $topCustomers = $this->salesTopCustomers($tenantId, $companyId, $branchId, $from, $to, $eligibleInvoiceStates);
        $branches = $this->salesBranchPerformance($tenantId, $companyId, $branchId, $from, $to, $eligibleInvoiceStates);
        $recent = $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereIn('state', $eligibleOrderStates)->whereBetween('created_at', [$from, $to])->with(['customer', 'items']), $companyId, $branchId)->latest()->limit(10)->get()->map(fn (SalesOrder $order) => ['id' => $order->id, 'number' => $order->number, 'customer' => $order->customer?->name ?? '—', 'date' => $order->created_at?->setTimezone($context['timezone'])->toDateString(), 'branch_id' => $order->branch_id, 'amount' => (string) ($order->base_total ?? $order->total), 'currency_code' => $currency?->code, 'status' => $order->state, 'payment_status' => null])->values()->all();
        $counts = collect(['draft', 'pending_approval', 'approved', 'processing', 'partially_fulfilled', 'fulfilled', 'delivered', 'cancelled'])->mapWithKeys(fn ($state) => [$state => (int) ($statusRows[$state] ?? 0)])->all();
        return response()->json(['data' => ['filters' => $this->analyticsFilters($context), 'metric_definitions' => ['revenue' => 'Eligible issued, partially paid and paid sales invoices less issued/applied customer credit notes, converted to the tenant base currency.', 'payments_received' => 'Verified customer payment allocations applied to scoped invoices during the selected period; unapplied verified receipts are excluded.', 'outstanding_ar' => 'As-of-date eligible invoice totals less recorded paid totals and eligible customer credit notes.', 'orders' => 'Sales orders in supported non-draft, non-cancelled workflow states.'], 'currency' => ['code' => $currency?->code, 'decimal_places' => $currency?->decimal_places, 'configured' => $currency !== null], 'kpis' => ['invoiced_revenue' => $this->kpi($revenue, $previousRevenue, $currency !== null, $currency ? null : 'Base accounting currency is not configured.'), 'sales_orders' => $this->kpi((string) $orderQuery->count(), (string) $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereIn('state', $eligibleOrderStates)->whereBetween('created_at', [$context['previous_from'], $context['previous_to']]), $companyId, $branchId)->count()), 'customers' => $this->metric((string) $customers), 'payments_received' => $this->metric($payments, $currency !== null, $currency ? null : 'Base accounting currency is not configured.'), 'outstanding_ar' => $this->metric($ar['outstanding'], $currency !== null, $currency ? null : 'Base accounting currency is not configured.'), 'pending_deliveries' => $this->metric((string) $this->pendingDeliveries($tenantId, $companyId, $branchId))], 'revenue_trend' => $this->salesRevenueTrend($tenantId, $companyId, $branchId, $from, $to, $context['timezone'], $currency), 'order_summary' => ['eligible_total' => array_sum($counts) - $counts['draft'] - $counts['cancelled'], 'statuses' => $counts, 'distribution' => collect($counts)->map(fn ($count, $state) => ['state' => $state, 'count' => $count])->values()->all()], 'top_products' => $this->topProducts($tenantId, $companyId, $branchId, $from, $to), 'top_customers' => $topCustomers, 'branch_performance' => $branches, 'receivables' => $ar, 'recent_orders' => $recent, 'pipeline' => ['available' => false, 'reason' => 'Opportunities are tenant-scoped in the current CRM schema and cannot be safely attributed to a company or branch.'], 'unavailable_sections' => $currency ? [] : ['revenue', 'payments_received', 'outstanding_ar']]]);
    }

    public function revenueTrend(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        if ($context['currency'] === null) return $this->analyticsUnavailable($context, 'Base accounting currency is not configured.');
        $granularity = $context['year'] !== null || $context['from']->diffInDays($context['to']) > 62 ? 'month' : 'day';
        $current = $this->revenueBuckets($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['from'], $context['to'], $context['timezone'], $granularity);
        $previous = $this->revenueBuckets($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['previous_from'], $context['previous_to'], $context['timezone'], $granularity);
        $points = [];
        $previousValues = array_values($previous['values']);
        foreach ($current['buckets'] as $index => $bucket) $points[] = ['key' => $bucket['key'], 'label' => $bucket['label'], 'current' => (string) ($current['values'][$bucket['key']] ?? '0'), 'previous' => (string) ($previousValues[$index] ?? '0')];
        return response()->json(['data' => [
            'available' => true,
            'reason' => null,
            'currency' => ['code' => $context['currency']->code, 'decimal_places' => $context['currency']->decimal_places],
            'accounting_basis' => 'posted_sales_invoices_less_valid_credit_notes',
            'granularity' => $granularity,
            'filters' => $this->analyticsFilters($context),
            'current_period' => ['from' => $context['from']->toDateString(), 'to' => $context['to']->toDateString()],
            'comparison_period' => ['from' => $context['previous_from']->toDateString(), 'to' => $context['previous_to']->toDateString()],
            'totals' => ['current' => $this->invoiceTotal($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['from'], $context['to']), 'previous' => $this->invoiceTotal($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['previous_from'], $context['previous_to'])],
            'points' => $points,
        ]]);
    }

    public function growth(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        if ($context['currency'] === null) return $this->analyticsUnavailable($context, 'Base accounting currency is not configured.');
        $current = $this->invoiceTotal($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['from'], $context['to']);
        $previous = $this->invoiceTotal($context['tenant_id'], $context['company_id'], $context['branch_id'], $context['previous_from'], $context['previous_to']);
        $growth = Decimal::compare($previous, '0') === 0 ? null : Decimal::mul(Decimal::div(Decimal::sub($current, $previous), $previous, 6), '100', 2);
        return response()->json(['data' => [
            'available' => true,
            'reason' => $growth === null ? 'No comparable prior-period revenue is available.' : null,
            'currency' => ['code' => $context['currency']->code, 'decimal_places' => $context['currency']->decimal_places],
            'accounting_basis' => 'posted_sales_invoices_less_valid_credit_notes',
            'filters' => $this->analyticsFilters($context),
            'current_period' => ['from' => $context['from']->toDateString(), 'to' => $context['to']->toDateString(), 'value' => $current],
            'previous_period' => ['from' => $context['previous_from']->toDateString(), 'to' => $context['previous_to']->toDateString(), 'value' => $previous],
            'growth_percent' => $growth,
            'target' => null,
        ]]);
    }

    public function financialPerformance(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        if ($context['currency'] === null) return $this->analyticsUnavailable($context, 'Base accounting currency is not configured.');
        if ($context['branch_id'] !== null) return $this->analyticsUnavailable($context, 'General ledger entries are company-scoped; branch financial reporting is not configured.');
        $granularity = $context['year'] !== null || $context['from']->diffInDays($context['to']) > 62 ? 'month' : 'day';
        $current = $this->ledgerBuckets($context['tenant_id'], $context['company_id'], $context['from'], $context['to'], $granularity);
        $previous = $this->ledgerBuckets($context['tenant_id'], $context['company_id'], $context['previous_from'], $context['previous_to'], $granularity);
        $points = [];
        $previousValues = array_values($previous['values']);
        foreach ($current['buckets'] as $index => $bucket) {
            $value = $current['values'][$bucket['key']] ?? ['income' => '0', 'expenses' => '0', 'profit' => '0'];
            $prior = $previousValues[$index] ?? ['income' => '0', 'expenses' => '0', 'profit' => '0'];
            $points[] = ['key' => $bucket['key'], 'label' => $bucket['label'], 'current' => $value, 'previous' => $prior];
        }
        return response()->json(['data' => [
            'available' => true,
            'reason' => null,
            'currency' => ['code' => $context['currency']->code, 'decimal_places' => $context['currency']->decimal_places],
            'accounting_basis' => 'posted_general_ledger_base_currency_by_chart_account_type',
            'granularity' => $granularity,
            'filters' => $this->analyticsFilters($context),
            'totals' => ['current' => $this->ledgerTotals($current), 'previous' => $this->ledgerTotals($previous)],
            'points' => $points,
        ]]);
    }

    public function recentTransactions(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        $limit = min(max($request->integer('limit', 8), 1), 10);
        $tenantId = $context['tenant_id'];
        $companyId = $context['company_id'];
        $branchId = $context['branch_id'];
        $from = $context['from'];
        $to = $context['to'];
        $baseCurrency = $context['currency']?->code;
        $rows = collect();

        $invoices = DB::table('sales_invoices as si')
            ->leftJoin('customers as c', 'c.id', '=', 'si.customer_id')
            ->leftJoin('accounting_currencies as ac', 'ac.id', '=', 'si.currency_id')
            ->where('si.tenant_id', $tenantId)
            ->whereIn('si.state', ['issued', 'partially_paid', 'paid'])
            ->whereBetween('si.created_at', [$from, $to])
            ->when($companyId, fn ($query) => $query->where('si.company_id', $companyId))
            ->when($branchId, fn ($query) => $query->where('si.branch_id', $branchId))
            ->orderByDesc('si.created_at')->limit($limit)
            ->get(['si.id', 'si.number as reference', 'c.name as party_name', 'si.created_at as occurred_at', 'si.total as amount', 'si.state as status', 'ac.code as currency_code'])
            ->map(fn ($row) => $this->transactionRow($row, 'sales_invoice', 'Sales invoice', 'inflow', $row->currency_code ?: $baseCurrency, '/crm-sales'));
        $rows = $rows->merge($invoices);

        $creditNotes = DB::table('credit_notes as cn')
            ->join('sales_invoices as si', 'si.id', '=', 'cn.invoice_id')
            ->leftJoin('customers as c', 'c.id', '=', 'cn.customer_id')
            ->leftJoin('accounting_currencies as ac', 'ac.id', '=', 'si.currency_id')
            ->where('cn.tenant_id', $tenantId)
            ->whereIn('cn.state', ['issued', 'applied'])
            ->whereBetween('cn.created_at', [$from, $to])
            ->when($companyId, fn ($query) => $query->where('si.company_id', $companyId))
            ->when($branchId, fn ($query) => $query->where('si.branch_id', $branchId))
            ->orderByDesc('cn.created_at')->limit($limit)
            ->get(['cn.id', 'cn.number as reference', 'c.name as party_name', 'cn.created_at as occurred_at', 'cn.total as amount', 'cn.state as status', 'ac.code as currency_code'])
            ->map(fn ($row) => $this->transactionRow($row, 'credit_note', 'Credit note / refund', 'outflow', $row->currency_code ?: $baseCurrency, '/crm-sales'));
        $rows = $rows->merge($creditNotes);

        $payments = DB::table('payments_received as pr')
            ->join('customers as c', 'c.id', '=', 'pr.customer_id')
            ->where('pr.tenant_id', $tenantId)
            ->where('pr.state', 'verified')
            ->whereBetween(DB::raw('COALESCE(pr.verified_at, pr.created_at)'), [$from, $to])
            ->when($companyId || $branchId, function ($query) use ($companyId, $branchId): void {
                $query->whereExists(function ($subquery) use ($companyId, $branchId): void {
                    $subquery->select(DB::raw('1'))->from('payment_allocations as pa')->join('sales_invoices as si2', 'si2.id', '=', 'pa.invoice_id')->whereColumn('pa.payment_id', 'pr.id')->when($companyId, fn ($q) => $q->where('si2.company_id', $companyId))->when($branchId, fn ($q) => $q->where('si2.branch_id', $branchId));
                });
            })
            ->orderByDesc(DB::raw('COALESCE(pr.verified_at, pr.created_at)'))->limit($limit)
            ->get(['pr.id', 'pr.reference', 'c.name as party_name', DB::raw('COALESCE(pr.verified_at, pr.created_at) as occurred_at'), 'pr.amount', 'pr.state as status'])
            ->map(fn ($row) => $this->transactionRow($row, 'customer_payment', 'Customer payment', 'inflow', $baseCurrency, '/crm-sales'));
        $rows = $rows->merge($payments);

        $supplierBills = DB::table('supplier_bills as sb')
            ->join('suppliers as s', 's.id', '=', 'sb.supplier_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'sb.purchase_order_id')
            ->leftJoin('accounting_currencies as ac', 'ac.id', '=', 'po.currency_id')
            ->where('sb.tenant_id', $tenantId)
            ->whereIn('sb.state', ['approved', 'posted'])
            ->whereBetween('sb.created_at', [$from, $to])
            ->when($companyId, fn ($query) => $query->where('po.company_id', $companyId))
            ->when($branchId, fn ($query) => $query->where('po.branch_id', $branchId))
            ->orderByDesc('sb.created_at')->limit($limit)
            ->get(['sb.id', 'sb.number as reference', 's.name as party_name', 'sb.created_at as occurred_at', 'sb.total as amount', 'sb.state as status', 'ac.code as currency_code'])
            ->map(fn ($row) => $this->transactionRow($row, 'supplier_bill', 'Supplier bill', 'outflow', $row->currency_code ?: $baseCurrency, '/purchasing'));
        $rows = $rows->merge($supplierBills);

        $journals = DB::table('journal_entries as je')
            ->join('journal_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
            ->leftJoin('accounting_currencies as ac', 'ac.id', '=', 'je.currency_id')
            ->where('je.tenant_id', $tenantId)->where('jl.tenant_id', $tenantId)->where('je.state', 'posted')
            ->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($companyId, fn ($query) => $query->where('je.company_id', $companyId))
            ->select('je.id', 'je.number as reference', 'je.description as party_name', 'je.entry_date as occurred_at', 'je.state as status', 'ac.code as currency_code')
            ->selectRaw('SUM(jl.debit) as amount')
            ->groupBy('je.id', 'je.number', 'je.description', 'je.entry_date', 'je.state', 'ac.code')
            ->orderByDesc('je.entry_date')->limit($limit)
            ->get()
            ->map(fn ($row) => $this->transactionRow($row, 'journal_entry', 'Accounting transaction', 'neutral', $row->currency_code ?: $baseCurrency, '/accounting'));
        $rows = $rows->merge($journals);

        $items = $rows->sortByDesc('occurred_at')->take($limit)->values()->all();
        return response()->json(['data' => [
            'available' => true,
            'reason' => null,
            'filters' => $this->analyticsFilters($context),
            'items' => $items,
            'pagination' => ['limit' => $limit, 'has_more' => count($items) === $limit],
        ]]);
    }

    public function topProductsRanking(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $context = $this->analyticsContext($request, $authorization);
        $filters = $request->validate(['ranking' => ['nullable', 'in:sales,volume'], 'limit' => ['nullable', 'integer', 'min:1', 'max:10']]);
        $ranking = $filters['ranking'] ?? 'sales';
        $limit = $filters['limit'] ?? 8;
        if ($ranking === 'sales' && $context['currency'] === null) return $this->rankingUnavailable($context, 'Base accounting currency is not configured.', $ranking, $limit);

        $invoiceRows = DB::table('sales_invoice_items as sii')
            ->join('sales_invoices as si', 'si.id', '=', 'sii.invoice_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sii.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('units_of_measure as u', 'u.id', '=', 'pv.unit_id')
            ->where('si.tenant_id', $context['tenant_id'])->where('sii.tenant_id', $context['tenant_id'])->where('pv.tenant_id', $context['tenant_id'])->where('p.tenant_id', $context['tenant_id'])->where('u.tenant_id', $context['tenant_id'])->whereNotIn('si.state', ['draft', 'cancelled', 'void'])
            ->whereBetween('si.created_at', [$context['from'], $context['to']])
            ->when($context['company_id'], fn ($query) => $query->where('si.company_id', $context['company_id']))
            ->when($context['branch_id'], fn ($query) => $query->where('si.branch_id', $context['branch_id']))
            ->select('si.id as invoice_id', 'p.id as product_id', 'pv.id as variant_id', 'p.name as product', 'pv.sku', 'u.name as unit_name')
            ->selectRaw('SUM(sii.quantity) as quantity')
            ->selectRaw('SUM(sii.line_total * COALESCE(si.exchange_rate, 1)) as revenue')
            ->groupBy('si.id', 'p.id', 'pv.id', 'p.name', 'pv.sku', 'u.name')
            ->get();

        $creditNotes = DB::table('credit_notes as cn')->join('sales_invoices as si', 'si.id', '=', 'cn.invoice_id')
            ->where('cn.tenant_id', $context['tenant_id'])->whereIn('cn.state', ['issued', 'applied'])->whereBetween('cn.created_at', [$context['from'], $context['to']])
            ->when($context['company_id'], fn ($query) => $query->where('si.company_id', $context['company_id']))
            ->when($context['branch_id'], fn ($query) => $query->where('si.branch_id', $context['branch_id']))
            ->select('cn.invoice_id')->selectRaw('SUM(cn.total * COALESCE(si.exchange_rate, 1)) as total')->groupBy('cn.invoice_id')->pluck('total', 'invoice_id');
        $returns = DB::table('sales_return_items as sri')->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')->join('sales_orders as so', 'so.id', '=', 'sr.sales_order_id')->join('sales_order_items as soi', 'soi.id', '=', 'sri.sales_order_item_id')->join('product_variants as pv', 'pv.id', '=', 'soi.variant_id')->join('products as p', 'p.id', '=', 'pv.product_id')->join('units_of_measure as u', 'u.id', '=', 'pv.unit_id')
            ->where('sr.tenant_id', $context['tenant_id'])->where('sri.tenant_id', $context['tenant_id'])->where('soi.tenant_id', $context['tenant_id'])->where('so.tenant_id', $context['tenant_id'])->where('pv.tenant_id', $context['tenant_id'])->where('p.tenant_id', $context['tenant_id'])->where('u.tenant_id', $context['tenant_id'])->where('sr.state', 'received')->whereBetween('sr.created_at', [$context['from'], $context['to']])
            ->when($context['company_id'], fn ($query) => $query->where('so.company_id', $context['company_id']))
            ->when($context['branch_id'], fn ($query) => $query->where('so.branch_id', $context['branch_id']))
            ->select('pv.id as variant_id', 'p.id as product_id', 'p.name as product', 'pv.sku', 'u.name as unit_name')->selectRaw('SUM(sri.quantity) as quantity')->selectRaw('SUM(sri.quantity * soi.unit_price * COALESCE(so.exchange_rate, 1)) as revenue')->groupBy('pv.id', 'p.id', 'p.name', 'pv.sku', 'u.name')->get();

        $invoiceGroups = $invoiceRows->groupBy('invoice_id');
        $products = [];
        foreach ($invoiceGroups as $invoiceId => $group) {
            $invoiceRevenue = '0';
            foreach ($group as $row) $invoiceRevenue = Decimal::add($invoiceRevenue, (string) $row->revenue);
            $creditTotal = (string) ($creditNotes[$invoiceId] ?? '0');
            foreach ($group as $row) {
                $key = $row->variant_id.'|'.$row->unit_name;
                $creditShare = Decimal::compare($invoiceRevenue, '0') === 0 ? '0' : Decimal::div(Decimal::mul($creditTotal, (string) $row->revenue), $invoiceRevenue, 6);
                $products[$key] ??= ['product_id' => $row->product_id, 'variant_id' => $row->variant_id, 'product' => $row->product, 'sku' => $row->sku, 'unit_name' => $row->unit_name, 'quantity' => '0', 'revenue' => '0'];
                $products[$key]['quantity'] = Decimal::add($products[$key]['quantity'], (string) $row->quantity);
                $products[$key]['revenue'] = Decimal::add($products[$key]['revenue'], Decimal::sub((string) $row->revenue, $creditShare));
            }
        }
        foreach ($returns as $row) {
            $key = $row->variant_id.'|'.$row->unit_name;
            $products[$key] ??= ['product_id' => $row->product_id, 'variant_id' => $row->variant_id, 'product' => $row->product, 'sku' => $row->sku, 'unit_name' => $row->unit_name, 'quantity' => '0', 'revenue' => '0'];
            $products[$key]['quantity'] = Decimal::sub($products[$key]['quantity'], (string) $row->quantity);
            $products[$key]['revenue'] = Decimal::sub($products[$key]['revenue'], (string) $row->revenue);
        }
        $products = collect($products)->map(function (array $row): array {
            $row['quantity'] = Decimal::compare($row['quantity'], '0') < 0 ? '0' : Decimal::normalize($row['quantity']);
            $row['revenue'] = Decimal::compare($row['revenue'], '0') < 0 ? '0' : Decimal::normalize($row['revenue']);
            return $row;
        })->filter(fn (array $row) => Decimal::compare($row[$ranking === 'sales' ? 'revenue' : 'quantity'], '0') > 0);
        $total = $products->reduce(fn (string $carry, array $row) => Decimal::add($carry, $ranking === 'sales' ? $row['revenue'] : $row['quantity']), '0');
        $max = $products->max(fn (array $row) => (float) $row[$ranking === 'sales' ? 'revenue' : 'quantity']) ?: 0;
        $products = $products->sort(fn (array $left, array $right) => Decimal::compare($right[$ranking === 'sales' ? 'revenue' : 'quantity'], $left[$ranking === 'sales' ? 'revenue' : 'quantity']))->values()->take($limit)->values();
        $items = $products->map(fn (array $row, int $index) => ['rank' => $index + 1, 'product_id' => $row['product_id'], 'variant_id' => $row['variant_id'], 'product' => $row['product'], 'sku' => $row['sku'], 'unit_name' => $row['unit_name'], 'quantity' => $row['quantity'], 'revenue' => $row['revenue'], 'currency_code' => $context['currency']?->code, 'percentage' => Decimal::compare($total, '0') === 0 ? null : Decimal::mul(Decimal::div($ranking === 'sales' ? $row['revenue'] : $row['quantity'], $total, 6), '100', 2), 'bar_percent' => $max <= 0 ? '0' : number_format(((float) $row[$ranking === 'sales' ? 'revenue' : 'quantity'] / $max) * 100, 2, '.', ''), 'detail_path' => '/catalog'])->all();
        return response()->json(['data' => ['available' => true, 'reason' => null, 'ranking' => $ranking, 'filters' => $this->analyticsFilters($context), 'methodology' => $ranking === 'sales' ? 'Posted sales invoice line revenue in base currency, less valid credit notes allocated proportionally to invoice lines and received sales returns.' : 'Posted sales invoice quantities, less received sales-return quantities; each SKU and unit of measure is ranked separately.', 'items' => $items, 'pagination' => ['limit' => $limit, 'has_more' => count($items) === $limit]]]);
    }

    private function salesRevenueTrend(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to, string $timezone, ?AccountingCurrency $currency): array
    {
        if (! $currency) return ['available' => false, 'reason' => 'Base accounting currency is not configured.', 'granularity' => null, 'points' => []];
        $granularity = $from->diffInDays($to) > 62 ? 'month' : 'day'; $series = $this->revenueBuckets($tenantId, $companyId, $branchId, $from, $to, $timezone, $granularity);
        return ['available' => true, 'reason' => null, 'granularity' => $granularity, 'points' => collect($series['buckets'])->map(fn ($bucket) => ['key' => $bucket['key'], 'label' => $bucket['label'], 'value' => (string) ($series['values'][$bucket['key']] ?? '0')])->all()];
    }

    private function receivablesSummary(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $asOf): array
    {
        $invoices = $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereIn('state', ['issued', 'partially_paid', 'paid'])->where('created_at', '<=', $asOf), $companyId, $branchId)->get(['id', 'customer_id', 'total', 'paid_total', 'state', 'created_at']);
        $creditNotes = DB::table('credit_notes')->where('tenant_id', $tenantId)->whereIn('state', ['issued', 'applied'])->where('created_at', '<=', $asOf)->select('invoice_id')->selectRaw('SUM(total) as total')->groupBy('invoice_id')->pluck('total', 'invoice_id');
        $outstanding = '0'; $unpaid = 0; $partial = 0; $overdue = 0; $dueSoon = 0; $today = $asOf->toDateString(); $soon = $asOf->addDays(7)->toDateString();
        foreach ($invoices as $invoice) {
            $balance = Decimal::sub(Decimal::sub((string) $invoice->total, (string) ($invoice->paid_total ?? '0')), (string) ($creditNotes[$invoice->id] ?? '0')); if (Decimal::compare($balance, '0') <= 0) continue; $outstanding = Decimal::add($outstanding, $balance); $invoice->state === 'issued' ? $unpaid++ : $partial++;
            if ($invoice->due_date ?? null) { if ($invoice->due_date < $today) $overdue++; elseif ($invoice->due_date <= $soon) $dueSoon++; }
        }
        return ['posted_invoice_count' => $invoices->count(), 'unpaid_invoices' => $unpaid, 'partially_paid_invoices' => $partial, 'overdue_invoices' => $overdue, 'due_soon' => $dueSoon, 'outstanding' => $outstanding, 'as_of' => $asOf->toDateString()];
    }

    private function salesTopCustomers(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to, array $states): array
    {
        return $this->scope(SalesInvoice::where('sales_invoices.tenant_id', $tenantId)->whereIn('sales_invoices.state', $states)->whereBetween('sales_invoices.created_at', [$from, $to])->join('customers', 'customers.id', '=', 'sales_invoices.customer_id'), $companyId, $branchId, 'sales_invoices')->select('customers.id', 'customers.name')->selectRaw('COUNT(DISTINCT sales_invoices.id) as order_count')->selectRaw('SUM(COALESCE(sales_invoices.base_total, sales_invoices.total)) as sales_amount')->selectRaw('MAX(sales_invoices.created_at) as last_purchase_at')->groupBy('customers.id', 'customers.name')->orderByDesc('sales_amount')->limit(10)->get()->map(fn ($row) => ['customer_id' => $row->id, 'customer' => $row->name, 'order_count' => (int) $row->order_count, 'sales_amount' => (string) $row->sales_amount, 'last_purchase_at' => CarbonImmutable::parse($row->last_purchase_at)->toDateString()])->values()->all();
    }

    private function salesBranchPerformance(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to, array $states): array
    {
        if (! $companyId) return ['available' => false, 'reason' => 'Select a company to view branch performance.', 'items' => []];
        $branches = Branch::where('tenant_id', $tenantId)->where('company_id', $companyId)->when($branchId, fn ($q) => $q->whereKey($branchId))->get(['id', 'name']);
        $rows = $this->scope(SalesInvoice::where('sales_invoices.tenant_id', $tenantId)->whereIn('sales_invoices.state', $states)->whereBetween('sales_invoices.created_at', [$from, $to])->join('branches', 'branches.id', '=', 'sales_invoices.branch_id'), $companyId, $branchId, 'sales_invoices')->select('branches.id', 'branches.name')->selectRaw('COUNT(DISTINCT sales_invoices.id) as order_count')->selectRaw('SUM(COALESCE(sales_invoices.base_total, sales_invoices.total)) as sales_amount')->groupBy('branches.id', 'branches.name')->get()->keyBy('id');
        return ['available' => true, 'reason' => null, 'items' => $branches->map(function ($branch) use ($rows): array { $row = $rows->get($branch->id); $amount = (string) ($row->sales_amount ?? '0'); $count = (int) ($row->order_count ?? 0); return ['branch_id' => $branch->id, 'branch' => $branch->name, 'sales_amount' => $amount, 'order_count' => $count, 'average_order_value' => $count ? Decimal::div($amount, (string) $count) : '0']; })->values()->all()];
    }

    private function transactionRow(object $row, string $type, string $typeLabel, string $direction, ?string $currency, ?string $viewAllPath): array
    {
        return ['id' => (string) $row->id, 'type' => $type, 'type_label' => $typeLabel, 'reference' => (string) $row->reference, 'party_name' => $row->party_name ?: '—', 'occurred_at' => CarbonImmutable::parse((string) $row->occurred_at)->toIso8601String(), 'amount' => (string) $row->amount, 'currency_code' => $currency, 'direction' => $direction, 'status' => (string) $row->status, 'detail_path' => null, 'view_all_path' => $viewAllPath];
    }

    private function rankingUnavailable(array $context, string $reason, string $ranking, int $limit): JsonResponse
    {
        return response()->json(['data' => ['available' => false, 'reason' => $reason, 'ranking' => $ranking, 'filters' => $this->analyticsFilters($context), 'methodology' => null, 'items' => [], 'pagination' => ['limit' => $limit, 'has_more' => false]]]);
    }

    private function analyticsContext(Request $request, AuthorizationService $authorization): array
    {
        $tenantId = (string) $request->header('X-Tenant-Id');
        $filters = $request->validate([
            'period' => ['nullable', 'in:today,last_7_days,last_30_days,this_month,this_year,custom'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date'],
            'company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'comparison_period' => ['nullable', 'in:previous_period,previous_year'],
        ]);
        $period = $filters['period'] ?? 'last_30_days';
        $dateFrom = $filters['date_from'] ?? $filters['from'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to'] ?? null;
        $companyId = $filters['company_id'] ?? null;
        $branchId = $filters['branch_id'] ?? null;
        abort_unless($authorization->allows($request->user(), $tenantId, 'reports.view', $companyId, $branchId), 403);
        if ($companyId) abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->exists(), 404);
        if ($branchId) abort_unless($companyId && Branch::where('tenant_id', $tenantId)->whereKey($branchId)->where('company_id', $companyId)->exists(), 404);
        $timezone = $companyId ? (string) Company::where('tenant_id', $tenantId)->whereKey($companyId)->value('timezone') : (string) config('app.timezone', 'UTC');
        if (! in_array($timezone, timezone_identifiers_list(), true)) $timezone = (string) config('app.timezone', 'UTC');
        $year = isset($filters['year']) ? (int) $filters['year'] : null;
        if ($year !== null) {
            $from = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone)->startOfYear();
            $to = $from->endOfYear();
            $comparison = 'previous_year';
        } else {
            if ($period === 'custom' && (! $dateFrom || ! $dateTo)) throw ValidationException::withMessages(['date_from' => ['Both date_from and date_to are required for a custom period.']]);
            if ($dateFrom && $dateTo && CarbonImmutable::parse($dateFrom)->gt(CarbonImmutable::parse($dateTo))) throw ValidationException::withMessages(['date_to' => ['date_to must be on or after date_from.']]);
            [$from, $to] = $this->range($period, ['from' => $dateFrom, 'to' => $dateTo], $timezone);
            $comparison = $filters['comparison_period'] ?? 'previous_period';
        }
        if ($comparison === 'previous_year') {
            $previousFrom = $from->subYear()->startOfDay();
            $previousTo = $to->subYear()->endOfDay();
        } else {
            $previousTo = $from->subDay()->endOfDay();
            $previousFrom = $previousTo->subDays($from->diffInDays($to))->startOfDay();
        }
        $currency = $companyId
            ? AccountingCurrency::query()
                ->where('tenant_id', $tenantId)
                ->where('code', Company::where('tenant_id', $tenantId)->whereKey($companyId)->value('base_currency'))
                ->first(['code', 'decimal_places'])
            : AccountingCurrency::where('tenant_id', $tenantId)->where('is_base', true)->first(['code', 'decimal_places']);
        return ['tenant_id' => $tenantId, 'company_id' => $companyId, 'branch_id' => $branchId, 'timezone' => $timezone, 'period' => $period, 'year' => $year, 'comparison_period' => $comparison, 'from' => $from, 'to' => $to, 'previous_from' => $previousFrom, 'previous_to' => $previousTo, 'currency' => $currency];
    }

    private function analyticsFilters(array $context): array
    {
        return ['period' => $context['period'], 'year' => $context['year'], 'comparison_period' => $context['comparison_period'], 'from' => $context['from']->toDateString(), 'to' => $context['to']->toDateString(), 'company_id' => $context['company_id'], 'branch_id' => $context['branch_id'], 'timezone' => $context['timezone']];
    }

    private function analyticsUnavailable(array $context, string $reason): JsonResponse
    {
        return response()->json(['data' => ['available' => false, 'reason' => $reason, 'currency' => $context['currency'] ? ['code' => $context['currency']->code, 'decimal_places' => $context['currency']->decimal_places] : ['code' => null, 'decimal_places' => null], 'accounting_basis' => null, 'granularity' => null, 'filters' => $this->analyticsFilters($context), 'points' => [], 'totals' => null, 'growth_percent' => null, 'target' => null]], 200);
    }

    private function revenueBuckets(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to, string $timezone, string $granularity): array
    {
        $format = $granularity === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';
        $invoiceExpression = "TO_CHAR(sales_invoices.created_at AT TIME ZONE ?, '{$format}')";
        $invoices = $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]), $companyId, $branchId)->selectRaw("{$invoiceExpression} as bucket, SUM(COALESCE(base_total, total)) as value", [$timezone])->groupBy('bucket')->pluck('value', 'bucket');
        $creditExpression = "TO_CHAR(cn.created_at AT TIME ZONE ?, '{$format}')";
        $creditNotes = DB::table('credit_notes as cn')->join('sales_invoices as si', 'si.id', '=', 'cn.invoice_id')->where('cn.tenant_id', $tenantId)->whereNotIn('cn.state', ['draft', 'cancelled', 'void'])->whereBetween('cn.created_at', [$from, $to])->when($companyId, fn ($q) => $q->where('si.company_id', $companyId))->when($branchId, fn ($q) => $q->where('si.branch_id', $branchId))->selectRaw("{$creditExpression} as bucket, SUM(cn.total * COALESCE(si.exchange_rate, 1)) as value", [$timezone])->groupBy('bucket')->pluck('value', 'bucket');
        $buckets = $this->analyticsBuckets($from, $to, $granularity);
        $values = [];
        foreach ($buckets as $bucket) $values[$bucket['key']] = Decimal::sub((string) ($invoices[$bucket['key']] ?? '0'), (string) ($creditNotes[$bucket['key']] ?? '0'));
        return ['buckets' => $buckets, 'values' => $values];
    }

    private function ledgerBuckets(string $tenantId, ?string $companyId, CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $format = $granularity === 'month' ? 'YYYY-MM' : 'YYYY-MM-DD';
        $rows = DB::table('journal_lines as jl')->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')->join('chart_accounts as ca', 'ca.id', '=', 'jl.account_id')->where('jl.tenant_id', $tenantId)->where('je.tenant_id', $tenantId)->where('je.state', 'posted')->whereBetween('je.entry_date', [$from->toDateString(), $to->toDateString()])->when($companyId, fn ($q) => $q->where('jl.company_id', $companyId))->selectRaw("TO_CHAR(je.entry_date, '{$format}') as bucket")->selectRaw("SUM(CASE WHEN ca.type = 'revenue' THEN jl.base_credit - jl.base_debit ELSE 0 END) as income")->selectRaw("SUM(CASE WHEN ca.type = 'expense' THEN jl.base_debit - jl.base_credit ELSE 0 END) as expenses")->groupBy('bucket')->get();
        $buckets = $this->analyticsBuckets($from, $to, $granularity);
        $values = [];
        foreach ($rows as $row) $values[$row->bucket] = ['income' => (string) $row->income, 'expenses' => (string) $row->expenses, 'profit' => Decimal::sub((string) $row->income, (string) $row->expenses)];
        return ['buckets' => $buckets, 'values' => $values];
    }

    private function ledgerTotals(array $series): array
    {
        $income = '0'; $expenses = '0';
        foreach ($series['values'] as $value) { $income = Decimal::add($income, $value['income']); $expenses = Decimal::add($expenses, $value['expenses']); }
        return ['income' => $income, 'expenses' => $expenses, 'profit' => Decimal::sub($income, $expenses)];
    }

    private function analyticsBuckets(CarbonImmutable $from, CarbonImmutable $to, string $granularity): array
    {
        $buckets = [];
        $cursor = $granularity === 'month' ? $from->startOfMonth() : $from->startOfDay();
        $end = $granularity === 'month' ? $to->startOfMonth() : $to->startOfDay();
        while ($cursor->lte($end)) { $key = $granularity === 'month' ? $cursor->format('Y-m') : $cursor->format('Y-m-d'); $buckets[] = ['key' => $key, 'label' => $granularity === 'month' ? $cursor->format('M Y') : $cursor->format('M j')]; $cursor = $granularity === 'month' ? $cursor->addMonth() : $cursor->addDay(); }
        return $buckets;
    }

    private function range(string $period, array $filters, string $timezone): array
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        return match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'yesterday' => [$today->subDay()->startOfDay(), $today->subDay()->endOfDay()],
            'last_7_days' => [$today->subDays(6)->startOfDay(), $today->endOfDay()],
            'last_month' => [$today->subMonth()->startOfMonth(), $today->subMonth()->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfDay()],
            'this_month' => [$today->startOfMonth(), $today->endOfDay()],
            'this_year' => [$today->startOfYear(), $today->endOfDay()],
            'custom' => [CarbonImmutable::parse($filters['from'], $timezone)->startOfDay(), CarbonImmutable::parse($filters['to'], $timezone)->endOfDay()],
            default => [$today->subDays(29)->startOfDay(), $today->endOfDay()],
        };
    }

    private function invoiceTotal(string $tenantId, ?string $companyId, ?string $branchId, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $query = SalesInvoice::where('tenant_id', $tenantId)->whereNotIn('state', ['draft', 'cancelled', 'void'])->whereBetween('created_at', [$from, $to]);
        $invoiced = (string) ($this->scope($query, $companyId, $branchId)->sum(DB::raw('COALESCE(base_total, total)')) ?: '0');
        $creditNotes = DB::table('credit_notes as cn')->join('sales_invoices as si', 'si.id', '=', 'cn.invoice_id')->where('cn.tenant_id', $tenantId)->whereNotIn('cn.state', ['draft', 'cancelled', 'void'])->whereBetween('cn.created_at', [$from, $to])->when($companyId, fn ($q) => $q->where('si.company_id', $companyId))->when($branchId, fn ($q) => $q->where('si.branch_id', $branchId))->sum(DB::raw('cn.total * COALESCE(si.exchange_rate, 1)'));
        return Decimal::sub($invoiced, (string) ($creditNotes ?: '0'));
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
        return $this->scope(SalesInvoice::where('tenant_id', $tenantId)->whereIn('state', ['issued', 'partially_paid'])->whereRaw('COALESCE(paid_total, 0) < COALESCE(total, 0)'), $companyId, $branchId)->count();
    }

    private function pendingDeliveries(string $tenantId, ?string $companyId, ?string $branchId): int
    {
        return $this->scope(SalesOrder::where('tenant_id', $tenantId)->whereIn('state', ['approved', 'partially_fulfilled'])->whereExists(function ($query): void {
            $query->select(DB::raw('1'))->from('sales_order_items as soi')->whereColumn('soi.sales_order_id', 'sales_orders.id')->whereRaw('COALESCE(soi.ordered_quantity, 0) > COALESCE(soi.delivered_quantity, 0) + COALESCE(soi.returned_quantity, 0)');
        }), $companyId, $branchId)->count();
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

    private function metric(string $value, bool $available = true, ?string $reason = null): array
    {
        return ['value' => $value, 'previous_value' => null, 'change_percent' => null, 'available' => $available, 'reason' => $reason];
    }

    private function kpi(string $value, string $previous, bool $available = true, ?string $reason = null): array
    {
        $change = Decimal::compare($previous, '0') === 0 ? null : Decimal::mul(Decimal::div(Decimal::sub($value, $previous), $previous, 6), '100', 2);
        return ['value' => $value, 'previous_value' => $previous, 'change_percent' => $change, 'available' => $available, 'reason' => $reason];
    }

    private function sampleSeries(array $series, int $max): array
    {
        if (count($series) <= $max) return $series;
        $step = (int) ceil(count($series) / $max);
        return array_values(array_filter($series, fn ($item, $index) => $index % $step === 0, ARRAY_FILTER_USE_BOTH));
    }
}
