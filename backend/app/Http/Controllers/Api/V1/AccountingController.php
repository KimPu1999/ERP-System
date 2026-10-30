<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{AccountingCurrency, BankAccount, ChartAccount, Company, FiscalPeriod, FiscalYear, JournalEntry};
use App\Services\{AccountingService, AuthorizationService};
use App\Support\TenantContext;
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
        $query = ChartAccount::query()->where('tenant_id', $tenantId);
        if ($scope !== null) $query->where(fn ($account) => $account->whereNull('company_id')->orWhereIn('company_id', $scope));
        return response()->json(['data' => $query->orderBy('code')->paginate(min(max($request->integer('per_page', 50), 10), 100))]);
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
        $data = $request->validate(['company_id' => 'nullable|uuid', 'code' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:200', 'type' => 'required|in:asset,liability,equity,revenue,expense', 'normal_balance' => 'required|in:debit,credit', 'is_control' => 'sometimes|boolean']);
        $companyId = $data['company_id'] ?? null;
        abort_unless($authorization->allows($request->user(), $tenantId, 'accounting.manage', $companyId), 403);
        if ($companyId) abort_unless(Company::where('tenant_id', $tenantId)->whereKey($companyId)->where('is_active', true)->exists(), 404);
        $account = DB::transaction(fn () => ChartAccount::create([...$data, 'tenant_id' => $tenantId]));
        return response()->json(['data' => $account], 201);
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
