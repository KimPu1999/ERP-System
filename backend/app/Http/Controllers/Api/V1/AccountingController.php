<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, BankAccount, ChartAccount, Company, FiscalPeriod, FiscalYear, JournalEntry};
use App\Services\{AccountingService, AuthorizationService};
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountingController
{
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

    public function financialReport(Request $request, AccountingService $accounting, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate(['company_id' => 'required|uuid', 'from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'type' => 'required|in:profit_loss,balance_sheet,cash_flow']);
        abort_unless($authorization->allows($request->user(), $this->tenant(), 'reports.view', $data['company_id']) || $authorization->allows($request->user(), $this->tenant(), 'accounting.manage', $data['company_id']), 403);
        if ($data['type'] === 'cash_flow') return response()->json(['data' => [], 'available' => false, 'reason' => 'Cash-flow classification is not configured for this tenant.']);
        $rows = $accounting->trialBalance($this->tenant(), $data['company_id'], $data['from'], $data['to']);
        $types = $data['type'] === 'profit_loss' ? ['revenue', 'expense'] : ['asset', 'liability', 'equity'];
        return response()->json(['data' => array_values(array_filter($rows, fn ($row) => in_array($row['account_type'] ?? null, $types, true))), 'available' => true, 'report_type' => $data['type']]);
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
