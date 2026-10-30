<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, AccountingIntegrationSetting, AccountMapping, BankAccount, ChartAccount, Company, FiscalPeriod, FiscalYear, JournalEntry, JournalLine};
use App\Services\{AccountingService, AuthorizationService, FinancialReportingService};
use App\Support\TenantContext;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountingController
{
    private const INTEGRATION_MAPPINGS = [
        'sales_revenue', 'accounts_receivable', 'cash', 'bank_clearing', 'sales_tax_payable', 'sales_discounts', 'sales_returns',
        'accounts_payable', 'inventory_asset', 'purchase_expense', 'input_tax', 'supplier_payment_clearing',
        'cogs', 'inventory_adjustment_expense', 'inventory_gain_loss', 'inventory_clearing',
        'salary_expense', 'salary_payable', 'payroll_tax_withholding', 'payroll_deductions', 'payroll_bank',
    ];

    public function integration(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $data = $request->validate(['company_id' => 'required|uuid']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.view', $data['company_id']) || $authorization->allows($request->user(), $tenantId, 'reports.view', $data['company_id']), 403);
        $company = Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->firstOrFail();
        $mappings = AccountMapping::where('tenant_id', $tenantId)->where('company_id', $company->id)->with('account')->get()->keyBy('mapping_key');
        $mappingRows = collect(self::INTEGRATION_MAPPINGS)->map(fn (string $key): array => ['key' => $key, 'account' => $mappings->get($key)?->account, 'configured' => $mappings->has($key)])->values();
        $required = ['sales_revenue', 'accounts_receivable', 'accounts_payable', 'purchase_expense', 'inventory_asset', 'cogs', 'salary_expense', 'salary_payable'];
        $missing = collect($required)->reject(fn (string $key): bool => $mappings->has($key))->values()->all();
        $settings = AccountingIntegrationSetting::firstOrCreate(['tenant_id' => $tenantId, 'company_id' => $company->id]);
        $lastPosted = JournalEntry::where('tenant_id', $tenantId)->where('company_id', $company->id)->where('state', 'posted')->max('posted_at');
        $openPeriod = FiscalPeriod::where('tenant_id', $tenantId)->whereHas('fiscalYear', fn ($query) => $query->where('company_id', $company->id))->where('state', 'open')->exists();
        return response()->json(['data' => ['company' => $company, 'automatic_posting_enabled' => $settings->automatic_posting_enabled, 'mappings' => $mappingRows, 'missing_required_mappings' => $missing, 'readiness' => ['base_currency' => (bool) $company->base_currency, 'account_mappings' => $missing === [], 'open_period' => $openPeriod, 'ready' => (bool) $company->base_currency && $missing === [] && $openPeriod && $settings->automatic_posting_enabled], 'modules' => $this->integrationModules($tenantId, $company->id, $lastPosted)]]);
    }

    public function updateIntegrationMappings(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $data = $request->validate(['company_id' => 'required|uuid', 'mappings' => 'required|array', 'mappings.*' => 'nullable|uuid']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $data['company_id']), 403);
        $company = Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->firstOrFail();
        DB::transaction(function () use ($data, $tenantId, $company): void {
            foreach ($data['mappings'] as $key => $accountId) {
                abort_unless(in_array($key, self::INTEGRATION_MAPPINGS, true), 422, 'Unsupported accounting mapping.');
                if ($accountId === null || $accountId === '') { AccountMapping::where('tenant_id', $tenantId)->where('company_id', $company->id)->where('mapping_key', $key)->delete(); continue; }
                $account = ChartAccount::where('tenant_id', $tenantId)->whereKey($accountId)->where('is_active', true)->firstOrFail();
                abort_unless($account->company_id === null || $account->company_id === $company->id, 422, 'Mapped account is outside the selected company.');
                abort_unless($account->posting_allowed !== false, 422, 'Mapped account must allow posting.');
                AccountMapping::updateOrCreate(['tenant_id' => $tenantId, 'company_id' => $company->id, 'mapping_key' => $key], ['account_id' => $account->id]);
            }
        });
        return $this->integration($request->merge(['company_id' => $company->id]), $authorization);
    }

    public function updateIntegrationSettings(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $data = $request->validate(['company_id' => 'required|uuid', 'automatic_posting_enabled' => 'required|boolean']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $data['company_id']), 403);
        $company = Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->firstOrFail();
        $mappingKeys = AccountMapping::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereIn('mapping_key', ['sales_revenue', 'accounts_receivable', 'accounts_payable', 'purchase_expense', 'inventory_asset', 'cogs', 'salary_expense', 'salary_payable'])->pluck('mapping_key');
        $currencyReady = (bool) $company->base_currency; $periodReady = FiscalPeriod::where('tenant_id', $tenantId)->whereHas('fiscalYear', fn ($query) => $query->where('company_id', $company->id))->where('state', 'open')->exists();
        abort_unless(! $data['automatic_posting_enabled'] || ($currencyReady && $periodReady && $mappingKeys->count() === 8), 422, 'Complete base currency, open period and required account mappings before enabling automatic posting.');
        $setting = AccountingIntegrationSetting::updateOrCreate(['tenant_id' => $tenantId, 'company_id' => $company->id], ['automatic_posting_enabled' => $data['automatic_posting_enabled'], 'enabled_at' => $data['automatic_posting_enabled'] ? now() : null, 'enabled_by' => $data['automatic_posting_enabled'] ? $request->user()->id : null]);
        return response()->json(['data' => $setting]);
    }

    private function integrationModules(string $tenantId, string $companyId, mixed $lastPosted): array
    {
        $sourceTypes = JournalEntry::where('tenant_id', $tenantId)->where('company_id', $companyId)->where('state', 'posted')->pluck('source_type')->filter()->values();
        $matches = ['sales' => ['Sales', 'Pos'], 'purchasing' => ['Supplier', 'Purchase'], 'inventory' => ['Inventory', 'Stock', 'GoodsReceipt'], 'payroll' => ['Payroll']];
        return collect(['sales' => 'Sales', 'purchasing' => 'Purchasing', 'inventory' => 'Inventory', 'payroll' => 'Payroll'])->map(function (string $name, string $key) use ($sourceTypes, $matches, $lastPosted): array {
            $detected = $sourceTypes->contains(fn ($source) => collect($matches[$key])->contains(fn (string $needle) => str_contains((string) $source, $needle)));
            return ['key' => $key, 'name' => $name, 'configuration_status' => 'Review required', 'posting_eligibility' => $detected ? 'Posted events detected' : 'No posted events detected', 'last_successful_posting' => $detected ? $lastPosted : null, 'failed_posting_count' => null, 'reconciliation_status' => 'Not verified'];
        })->values()->all();
    }
    public function accounts(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $companyId = $request->string('company_id')->toString() ?: null;
        $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = ChartAccount::query()->where('tenant_id', $tenantId)->with('parent')->withCount(['children', 'journalLines']);
        if ($scope !== null) $query->where(fn ($account) => $account->whereNull('company_id')->orWhereIn('company_id', $scope));
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner->where('code', 'ilike', '%'.$request->string('search').'%')->orWhere('name', 'ilike', '%'.$request->string('search').'%')));
        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')));
        $query->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status')->toString() === 'active'));
        $sort = in_array($request->string('sort')->toString(), ['code', 'name', 'type', 'created_at'], true) ? $request->string('sort')->toString() : 'code';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        return response()->json(['data' => $query->orderBy($sort, $direction)->paginate(min(max($request->integer('per_page', 50), 10), 100))]);
    }

    public function currencies(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $this->readCompanies($request, $authorization, $tenantId, null);
        return response()->json(['data' => AccountingCurrency::where('tenant_id', $tenantId)->orderBy('code')->get()]);
    }

    public function bankAccounts(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $companyId = $request->string('company_id')->toString() ?: null;
        $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = BankAccount::where('tenant_id', $tenantId)->where('status', 'active')->with('currency')->when($scope !== null, fn ($q) => $q->whereIn('company_id', $scope));
        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function storeAccount(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $data = $request->validate(['company_id' => 'nullable|uuid', 'code' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:200', 'type' => 'required|in:asset,liability,equity,revenue,expense', 'subtype' => 'nullable|string|max:100', 'parent_id' => 'nullable|uuid', 'description' => 'nullable|string|max:5000', 'normal_balance' => 'required|in:debit,credit', 'is_control' => 'sometimes|boolean', 'posting_allowed' => 'sometimes|boolean', 'is_active' => 'sometimes|boolean']);
        $companyId = $data['company_id'] ?? null;
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $companyId), 403);
        if ($companyId) abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->where('is_active', true)->exists(), 404);
        $this->validateAccountParent($data['parent_id'] ?? null, $tenantId, $companyId, $data['type']);
        $account = DB::transaction(function () use ($data, $tenantId, $companyId, $request): ChartAccount {
            abort_unless(! ChartAccount::where('tenant_id', $tenantId)->where('company_id', $companyId)->where('code', $data['code'])->exists(), 409);
            return ChartAccount::create([...$data, 'tenant_id' => $tenantId, 'company_id' => $companyId, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        });
        return response()->json(['data' => $account], 201);
    }

    public function account(Request $request, ChartAccount $account, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeAccount($request, $account, $authorization, 'accounting.view');
        return response()->json(['data' => $account->load(['parent', 'children'])->loadCount(['children', 'journalLines'])]);
    }

    public function updateAccount(Request $request, ChartAccount $account, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeAccount($request, $account, $authorization, 'accounting.manage');
        $data = $request->validate(['code' => 'sometimes|alpha_dash|max:40', 'name' => 'sometimes|string|max:200', 'type' => 'sometimes|in:asset,liability,equity,revenue,expense', 'subtype' => 'nullable|string|max:100', 'parent_id' => 'nullable|uuid', 'description' => 'nullable|string|max:5000', 'normal_balance' => 'sometimes|in:debit,credit', 'is_control' => 'sometimes|boolean', 'posting_allowed' => 'sometimes|boolean']);
        $referenced = $account->journalLines()->exists();
        if ($referenced && array_intersect(array_keys($data), ['code', 'type', 'parent_id', 'normal_balance', 'is_control', 'posting_allowed'])) throw ValidationException::withMessages(['account' => ['Accounting-critical fields cannot be changed after the account is referenced by a journal.']]);
        $type = $data['type'] ?? $account->type;
        $this->validateAccountParent($data['parent_id'] ?? $account->parent_id, $account->tenant_id, $account->company_id, $type, $account->id);
        if (isset($data['code']) && ChartAccount::where('tenant_id', $account->tenant_id)->where('company_id', $account->company_id)->where('code', $data['code'])->where($account->getKeyName(), '!=', $account->id)->exists()) throw ValidationException::withMessages(['code' => ['The account code is already used in this company.']]);
        $account->update([...$data, 'updated_by' => $request->user()->id]);
        return response()->json(['data' => $account->fresh(['parent', 'children'])]);
    }

    public function accountStatus(Request $request, ChartAccount $account, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeAccount($request, $account, $authorization, 'accounting.manage');
        $data = $request->validate(['is_active' => 'required|boolean']);
        if (! $data['is_active'] && $account->journalLines()->exists()) {
            // Deactivation is safe for history; posted references remain immutable.
        }
        $account->update(['is_active' => $data['is_active'], 'updated_by' => $request->user()->id]);
        return response()->json(['data' => $account->fresh()]);
    }

    public function accountHierarchy(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $companyId = $request->string('company_id')->toString() ?: null; $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $rows = ChartAccount::where('tenant_id', $tenantId)->when($scope !== null, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('company_id')->orWhereIn('company_id', $scope)))->withCount(['children', 'journalLines'])->orderBy('code')->get();
        return response()->json(['data' => $rows]);
    }

    public function accountSummary(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $companyId = $request->string('company_id')->toString() ?: null; $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = ChartAccount::where('tenant_id', $tenantId)->when($scope !== null, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('company_id')->orWhereIn('company_id', $scope)));
        return response()->json(['data' => ['total' => (clone $query)->count(), 'active' => (clone $query)->where('is_active', true)->count(), 'inactive' => (clone $query)->where('is_active', false)->count(), 'posting' => (clone $query)->where('posting_allowed', true)->count(), 'by_type' => (clone $query)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')]]);
    }

    public function periods(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $companyId = $request->string('company_id')->toString() ?: null;
        $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = FiscalPeriod::where('tenant_id', $tenantId)->with('fiscalYear')->when($scope !== null, fn ($q) => $q->whereHas('fiscalYear', fn ($year) => $year->whereIn('company_id', $scope)));
        return response()->json(['data' => $query->orderByDesc('starts_on')->paginate(min(max($request->integer('per_page', 50), 10), 100))]);
    }

    public function storePeriod(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $data = $request->validate(['company_id' => 'required|uuid', 'year_name' => 'required|string|max:100', 'period_name' => 'required|string|max:100', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $data['company_id']), 403);
        abort_unless(Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->exists(), 404);
        $period = DB::transaction(function () use ($data, $tenantId): FiscalPeriod {
            $overlap = FiscalPeriod::where('tenant_id', $tenantId)->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $data['company_id']))->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists();
            if ($overlap) throw ValidationException::withMessages(['starts_on' => ['The accounting period overlaps an existing period for this company.']]);
            $year = FiscalYear::where('tenant_id', $tenantId)->where('company_id', $data['company_id'])->whereDate('starts_on', '<=', $data['starts_on'])->whereDate('ends_on', '>=', $data['ends_on'])->lockForUpdate()->first();
            if (! $year) $year = FiscalYear::create(['tenant_id' => $tenantId, 'company_id' => $data['company_id'], 'name' => $data['year_name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'state' => 'open']);
            return FiscalPeriod::create(['tenant_id' => $tenantId, 'fiscal_year_id' => $year->id, 'name' => $data['period_name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'state' => 'open']);
        });
        return response()->json(['data' => $period->load('fiscalYear')], 201);
    }

    public function fiscalYears(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $companyId = $request->string('company_id')->toString() ?: null; $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = FiscalYear::where('tenant_id', $tenantId)->withCount('periods')->when($scope !== null, fn ($q) => $q->whereIn('company_id', $scope));
        return response()->json(['data' => $query->orderByDesc('starts_on')->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function storeFiscalYear(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $data = $request->validate(['company_id' => 'required|uuid', 'name' => 'required|string|max:100', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after:starts_on']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenantId)->whereKey($data['company_id'])->where('is_active', true)->exists(), 404);
        $year = DB::transaction(function () use ($data, $tenantId): FiscalYear { if (FiscalYear::where('tenant_id', $tenantId)->where('company_id', $data['company_id'])->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists()) throw ValidationException::withMessages(['starts_on' => ['The fiscal year overlaps an existing fiscal year.']]); return FiscalYear::create([...$data, 'tenant_id' => $tenantId, 'state' => 'open']); });
        return response()->json(['data' => $year->load('periods')], 201);
    }

    public function generatePeriods(Request $request, FiscalYear $fiscalYear, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); abort_unless($fiscalYear->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $fiscalYear->company_id), 403);
        $periods = DB::transaction(function () use ($fiscalYear, $tenantId): array { $year = FiscalYear::where('tenant_id', $tenantId)->whereKey($fiscalYear->id)->lockForUpdate()->firstOrFail(); if ($year->state !== 'open') throw ValidationException::withMessages(['state' => ['Periods cannot be generated for a closed fiscal year.']]); if ($year->periods()->exists()) throw ValidationException::withMessages(['periods' => ['Periods already exist for this fiscal year.']]); $cursor = CarbonImmutable::parse($year->starts_on->toDateString()); $end = CarbonImmutable::parse($year->ends_on->toDateString()); $created = []; while ($cursor->lte($end)) { $periodEnd = $cursor->endOfMonth()->min($end); $created[] = FiscalPeriod::create(['tenant_id' => $tenantId, 'fiscal_year_id' => $year->id, 'name' => $cursor->format('F Y'), 'starts_on' => $cursor->toDateString(), 'ends_on' => $periodEnd->toDateString(), 'state' => 'open']); $cursor = $periodEnd->addDay(); } return $created; });
        return response()->json(['data' => $periods], 201);
    }

    public function closePeriod(Request $request, FiscalPeriod $period, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        abort_unless($period->tenant_id === $tenantId, 404);
        $period->load('fiscalYear');
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.close', $period->fiscalYear->company_id), 403);
        $closed = DB::transaction(function () use ($period, $tenantId, $request): FiscalPeriod {
            $locked = FiscalPeriod::where('tenant_id', $tenantId)->whereKey($period->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'open') throw ValidationException::withMessages(['state' => ['Only an open period can be closed.']]);
            $locked->update(['state' => 'closed', 'closed_at' => now(), 'closed_by' => $request->user()->id]);
            return $locked->fresh('fiscalYear');
        });
        return response()->json(['data' => $closed]);
    }

    public function post(Request $request, AccountingService $accounting, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        $data = $request->validate(['company_id' => 'required|uuid', 'fiscal_period_id' => 'required|uuid', 'currency_id' => 'required|uuid', 'entry_date' => 'required|date', 'idempotency_key' => 'required|string|max:100', 'exchange_rate' => 'nullable|regex:/^\d+(\.\d{1,12})?$/', 'description' => 'nullable|string', 'source_type' => 'nullable|string|max:100', 'source_id' => 'nullable|uuid', 'lines' => 'required|array|min:2', 'lines.*.account_id' => 'required|uuid', 'lines.*.debit' => 'nullable|regex:/^\d+(\.\d{1,6})?$/', 'lines.*.credit' => 'nullable|regex:/^\d+(\.\d{1,6})?$/', 'lines.*.description' => 'nullable|string']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.post', $data['company_id']), 403);
        $entry = $accounting->post([...$data, 'tenant_id' => $tenantId, 'created_by' => $request->user()->id]);
        return response()->json(['data' => $entry], 201);
    }

    public function reopenPeriod(Request $request, FiscalPeriod $period, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); abort_unless($period->tenant_id === $tenantId, 404); $period->load('fiscalYear'); abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.close', $period->fiscalYear->company_id), 403);
        $period = DB::transaction(function () use ($period, $tenantId): FiscalPeriod { $locked = FiscalPeriod::where('tenant_id', $tenantId)->whereKey($period->id)->lockForUpdate()->firstOrFail(); if ($locked->state === 'open') throw ValidationException::withMessages(['state' => ['The period is already open.']]); $locked->update(['state' => 'open', 'closed_at' => null, 'closed_by' => null]); return $locked->fresh('fiscalYear'); }); return response()->json(['data' => $period]);
    }

    public function lockPeriod(Request $request, FiscalPeriod $period, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); abort_unless($period->tenant_id === $tenantId, 404); $period->load('fiscalYear'); abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.close', $period->fiscalYear->company_id), 403);
        $period = DB::transaction(function () use ($period, $tenantId, $request): FiscalPeriod { $locked = FiscalPeriod::where('tenant_id', $tenantId)->whereKey($period->id)->lockForUpdate()->firstOrFail(); if ($locked->state !== 'closed') throw ValidationException::withMessages(['state' => ['Only a closed period can be locked.']]); $locked->update(['state' => 'locked', 'closed_by' => $request->user()->id, 'closed_at' => $locked->closed_at ?: now()]); return $locked->fresh('fiscalYear'); }); return response()->json(['data' => $period]);
    }

    public function journals(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $companyId = $request->string('company_id')->toString() ?: null; $scope = $this->readCompanies($request, $authorization, $tenantId, $companyId);
        $query = JournalEntry::where('tenant_id', $tenantId)->with(['period', 'lines.account'])->when($scope !== null, fn ($q) => $q->whereIn('company_id', $scope))->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner->where('number', 'ilike', '%'.$request->string('search').'%')->orWhere('description', 'ilike', '%'.$request->string('search').'%')));
        return response()->json(['data' => $query->orderByDesc('entry_date')->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function journal(Request $request, JournalEntry $entry, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeJournal($request, $entry, $authorization, 'accounting.view');
        return response()->json(['data' => $entry->load(['period.fiscalYear', 'lines.account', 'reversal'])]);
    }

    public function storeJournalDraft(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant(); $data = $request->validate(['company_id' => 'required|uuid', 'fiscal_period_id' => 'required|uuid', 'currency_id' => 'required|uuid', 'entry_date' => 'required|date', 'description' => 'nullable|string|max:5000', 'reference' => 'nullable|string|max:200', 'source_type' => 'nullable|string|max:100', 'source_id' => 'nullable|uuid', 'exchange_rate' => 'nullable|regex:/^\\d+(\\.\\d{1,12})?$/', 'idempotency_key' => 'required|string|max:100', 'lines' => 'required|array|min:2', 'lines.*.account_id' => 'required|uuid', 'lines.*.debit' => 'nullable|regex:/^\\d+(\\.\\d{1,6})?$/', 'lines.*.credit' => 'nullable|regex:/^\\d+(\\.\\d{1,6})?$/', 'lines.*.description' => 'nullable|string|max:1000']);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.create', $data['company_id']) || $authorization->allows($request->user(), $tenantId, 'accounting.manage', $data['company_id']), 403);
        $entry = DB::transaction(function () use ($data, $tenantId, $request): JournalEntry {
            $period = FiscalPeriod::where('tenant_id', $tenantId)->whereKey($data['fiscal_period_id'])->whereHas('fiscalYear', fn ($q) => $q->where('company_id', $data['company_id']))->firstOrFail();
            if ($data['entry_date'] < $period->starts_on->toDateString() || $data['entry_date'] > $period->ends_on->toDateString()) throw ValidationException::withMessages(['entry_date' => ['The entry date is outside the selected fiscal period.']]);
            if (JournalEntry::where('tenant_id', $tenantId)->where('idempotency_key', $data['idempotency_key'])->exists()) throw ValidationException::withMessages(['idempotency_key' => ['This journal request has already been used.']]);
            $debit = '0'; $credit = '0'; $lines = [];
            foreach ($data['lines'] as $line) {
                $d = Decimal::normalize((string) ($line['debit'] ?? '0')); $c = Decimal::normalize((string) ($line['credit'] ?? '0'));
                if ((Decimal::compare($d, '0') <= 0 && Decimal::compare($c, '0') <= 0) || (Decimal::compare($d, '0') > 0 && Decimal::compare($c, '0') > 0)) throw ValidationException::withMessages(['lines' => ['Each line must contain exactly one positive debit or credit.']]);
                $account = ChartAccount::where('tenant_id', $tenantId)->whereKey($line['account_id'])->where('is_active', true)->where('posting_allowed', true)->firstOrFail();
                if ($account->company_id !== null && $account->company_id !== $data['company_id']) throw ValidationException::withMessages(['lines' => ['An account is outside the selected company scope.']]);
                $debit = Decimal::add($debit, $d); $credit = Decimal::add($credit, $c); $lines[] = [$account, $d, $c, $line['description'] ?? null];
            }
            if (count($lines) < 2 || Decimal::compare($debit, $credit) !== 0) throw ValidationException::withMessages(['lines' => ['Journal entries must have at least two lines and equal total debits and credits.']]);
            $entry = JournalEntry::create(['tenant_id' => $tenantId, 'company_id' => $data['company_id'], 'fiscal_period_id' => $period->id, 'currency_id' => $data['currency_id'], 'created_by' => $request->user()->id, 'number' => 'JE-DRAFT-'.now()->format('Ym').'-'.strtoupper(bin2hex(random_bytes(3))), 'entry_date' => $data['entry_date'], 'state' => 'draft', 'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null, 'idempotency_key' => $data['idempotency_key'], 'exchange_rate' => $data['exchange_rate'] ?? '1', 'description' => $data['description'] ?? null, 'reference' => $data['reference'] ?? null]);
            foreach ($lines as [$account, $d, $c, $description]) JournalLine::create(['tenant_id' => $tenantId, 'journal_entry_id' => $entry->id, 'account_id' => $account->id, 'company_id' => $data['company_id'], 'debit' => $d, 'credit' => $c, 'base_debit' => Decimal::mul($d, (string) ($data['exchange_rate'] ?? '1')), 'base_credit' => Decimal::mul($c, (string) ($data['exchange_rate'] ?? '1')), 'description' => $description]);
            return $entry;
        });
        return response()->json(['data' => $entry->load('lines')], 201);
    }

    public function submitJournal(Request $request, JournalEntry $entry, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeJournal($request, $entry, $authorization, 'accounting.submit');
        abort_unless($entry->state === 'draft', 422, 'Only draft journals can be submitted.');
        $entry->update(['state' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
        return response()->json(['data' => $entry->fresh('lines')]);
    }

    public function approveJournal(Request $request, JournalEntry $entry, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeJournal($request, $entry, $authorization, 'accounting.approve');
        abort_unless($entry->state === 'submitted', 422, 'Only submitted journals can be approved.');
        abort_unless($entry->created_by !== $request->user()->id, 403, 'The journal creator cannot approve their own entry.');
        $entry->update(['state' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        return response()->json(['data' => $entry->fresh('lines')]);
    }

    public function rejectJournal(Request $request, JournalEntry $entry, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeJournal($request, $entry, $authorization, 'accounting.approve');
        abort_unless(in_array($entry->state, ['submitted', 'approved'], true), 422, 'Only pending journals can be rejected.');
        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $entry->update(['state' => 'rejected', 'rejected_by' => $request->user()->id, 'rejected_at' => now(), 'rejection_reason' => $data['reason']]);
        return response()->json(['data' => $entry->fresh('lines')]);
    }

    public function postJournal(Request $request, JournalEntry $entry, AccountingService $accounting, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeJournal($request, $entry, $authorization, 'accounting.post');
        return response()->json(['data' => $accounting->postDraft($entry, $request->user()->id)], 201);
    }

    public function ledger(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'account_id' => 'nullable|uuid', 'search' => 'nullable|string|max:100']);
        abort_unless($authorization->allows($request->user(), $this->tenant(), 'accounting.view', $data['company_id']) || $authorization->allows($request->user(), $this->tenant(), 'reports.view', $data['company_id']), 403);
        $query = JournalLine::with(['account', 'entry'])->where('tenant_id', $this->tenant())->where('company_id', $data['company_id'])->whereHas('entry', fn ($q) => $q->where('state', 'posted')->whereBetween('entry_date', [$data['from'], $data['to']]))->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $data['account_id']))->when($request->filled('search'), fn ($q) => $q->whereHas('entry', fn ($inner) => $inner->where('number', 'ilike', '%'.$data['search'].'%')->orWhere('description', 'ilike', '%'.$data['search'].'%')));
        return response()->json(['data' => $query->orderBy('created_at')->paginate(min(max($request->integer('per_page', 50), 10), 100))]);
    }

    public function financialStatements(Request $request, FinancialReportingService $reporting, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'report' => 'required|in:trial_balance,profit_loss,balance_sheet', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'as_of' => 'nullable|date']);
        abort_unless($authorization->allows($request->user(), $this->tenant(), 'reports.view', $data['company_id']) || $authorization->allows($request->user(), $this->tenant(), 'accounting.view', $data['company_id']), 403);
        $from = $data['from'] ?? now()->startOfYear()->toDateString(); $to = $data['to'] ?? now()->toDateString();
        $result = match ($data['report']) { 'trial_balance' => $reporting->trialBalance($this->tenant(), $data['company_id'], $from, $to), 'profit_loss' => $reporting->profitAndLoss($this->tenant(), $data['company_id'], $from, $to), 'balance_sheet' => $reporting->balanceSheet($this->tenant(), $data['company_id'], $data['as_of'] ?? $to) };
        return response()->json(['data' => $result, 'report' => $data['report'], 'currency_policy' => 'base_currency_posted_ledger']);
    }

    public function reverse(Request $request, JournalEntry $entry, AccountingService $accounting, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->tenant();
        abort_unless($entry->tenant_id === $tenantId, 404);
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.post', $entry->company_id), 403);
        $data = $request->validate(['idempotency_key' => 'required|string|max:100']);
        return response()->json(['data' => $accounting->reverse($entry, $data['idempotency_key'], $request->user()->id)], 201);
    }

    public function trialBalance(Request $request, AccountingService $accounting, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);
        abort_unless($authorization->allows($request->user(), $this->tenant(), 'reports.view', $data['company_id']) || $authorization->allows($request->user(), $this->tenant(), 'accounting.manage', $data['company_id']), 403);
        return response()->json(['data' => $accounting->trialBalance($this->tenant(), $data['company_id'], $data['from'], $data['to'])]);
    }

    public function financialReport(Request $request, FinancialReportingService $reporting, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'type' => 'required|in:profit_loss,balance_sheet,cash_flow']);
        abort_unless($authorization->allows($request->user(), $this->tenant(), 'reports.view', $data['company_id']) || $authorization->allows($request->user(), $this->tenant(), 'accounting.manage', $data['company_id']), 403);
        if ($data['type'] === 'cash_flow') return response()->json(['data' => [], 'available' => false, 'reason' => 'Cash-flow classification is not configured for this tenant.']);
        $result = $data['type'] === 'profit_loss' ? $reporting->profitAndLoss($this->tenant(), $data['company_id'], $data['from'], $data['to']) : $reporting->balanceSheet($this->tenant(), $data['company_id'], $data['to']);
        return response()->json(['data' => $result, 'available' => true, 'report_type' => $data['type']]);
    }

    private function authorizeAccount(Request $request, ChartAccount $account, AuthorizationService $authorization, string $permission): void
    {
        $tenantId = $this->tenant(); abort_unless($account->tenant_id === $tenantId, 404);
        $allowed = $authorization->allows($request->user(), $tenantId, $permission, $account->company_id) || ($permission === 'accounting.view' && $authorization->allows($request->user(), $tenantId, 'reports.view', $account->company_id));
        abort_unless($allowed, 403);
    }

    private function validateAccountParent(?string $parentId, string $tenantId, ?string $companyId, string $type, ?string $accountId = null): void
    {
        if (! $parentId) return;
        $parent = ChartAccount::where('tenant_id', $tenantId)->whereKey($parentId)->first();
        abort_unless($parent && $parent->company_id === $companyId && $parent->type === $type && $parent->id !== $accountId, 422, 'Parent account must belong to the same company and classification.');
        $visited = [];
        while ($parent) {
            if (isset($visited[$parent->id])) abort(422, 'Account hierarchy contains a cycle.');
            $visited[$parent->id] = true;
            if ($parent->id === $accountId) throw ValidationException::withMessages(['parent_id' => ['An account cannot be placed below its descendant.']]);
            $parent = $parent->parent_id ? ChartAccount::where('tenant_id', $tenantId)->whereKey($parent->parent_id)->first() : null;
        }
    }

    private function authorizeJournal(Request $request, JournalEntry $entry, AuthorizationService $authorization, string $permission): void
    {
        $tenantId = $this->tenant(); abort_unless($entry->tenant_id === $tenantId, 404);
        $allowed = $authorization->allows($request->user(), $tenantId, $permission, $entry->company_id) || ($permission === 'accounting.view' && $authorization->allows($request->user(), $tenantId, 'reports.view', $entry->company_id));
        abort_unless($allowed, 403);
    }

    private function tenant(): string
    {
        return (string) app(TenantContext::class)->id();
    }

    private function readCompanies(Request $request, AuthorizationService $authorization, string $tenantId, ?string $companyId): ?array
    {
        $permissions = ['accounting.manage', 'accounting.post', 'reports.view'];
        if ($companyId) {
            abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->where('is_active', true)->exists(), 404);
            foreach ($permissions as $permission) if ($authorization->allows($request->user(), $tenantId, $permission, $companyId)) return [$companyId];
            abort(403);
        }
        $ids = [];
        foreach ($permissions as $permission) {
            $accessible = $authorization->accessibleCompanyIds($request->user(), $tenantId, $permission);
            if ($accessible === null) return null;
            $ids = [...$ids, ...$accessible];
        }
        $ids = array_values(array_unique($ids));
        abort_unless($ids !== [], 403);
        return $ids;
    }
}
