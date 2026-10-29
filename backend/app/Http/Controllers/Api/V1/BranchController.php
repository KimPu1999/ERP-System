<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PlatformAuditLog;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BranchController
{
    private const READ_PERMISSIONS = ['organization.manage', 'company.manage', 'reports.view'];
    private const MANAGE_PERMISSIONS = ['organization.manage', 'company.manage'];

    public function index(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->assertCompanyAccess($request, $company, $authorization, false);
        $query = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $company->id)
            ->with('manager:id,name,email,status')
            ->withCount(['employees as staff_count' => fn (Builder $employee) => $employee->where('status', 'active')]);
        $this->scopeToAuthorizedBranches($query, $request, $company, $authorization);

        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'active' => (clone $summaryQuery)->where('status', 'active')->count(),
            'inactive' => (clone $summaryQuery)->where('status', 'inactive')->count(),
            'assigned_managers' => (clone $summaryQuery)->whereNotNull('manager_user_id')->distinct('manager_user_id')->count('manager_user_id'),
        ];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') $query->where(fn (Builder $branch) => $branch->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"));
        if ($request->filled('status') && in_array($request->string('status')->toString(), ['active', 'inactive', 'archived'], true)) $query->where('status', $request->string('status')->toString());
        if ($request->filled('city')) $query->where('city', $request->string('city')->toString());
        if ($request->filled('region')) $query->where('region', $request->string('region')->toString());
        if ($request->filled('manager_user_id')) $query->where('manager_user_id', $request->string('manager_user_id')->toString());

        $sortMap = ['name' => 'name', 'code' => 'code', 'city' => 'city', 'region' => 'region', 'status' => 'status', 'created_at' => 'created_at'];
        $sort = $sortMap[$request->string('sort', 'created_at')->toString()] ?? 'created_at';
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $perPage = min(max($request->integer('per_page', 25), 10), 100);

        return response()->json(['data' => $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString(), 'summary' => $summary]);
    }

    public function dashboardIndex(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->assertCompanyAccess($request, $company, $authorization, false);
        $query = Branch::query()->where('tenant_id', $tenantId)->where('company_id', $company->id)->where('status', 'active')->orderBy('name');
        $this->scopeToAuthorizedBranches($query, $request, $company, $authorization);
        return response()->json(['data' => $query->paginate(100)]);
    }

    public function managerCandidates(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompanyAccess($request, $company, $authorization, true);
        $users = User::where('status', 'active')->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $company->tenant_id)->where('status', 'active'))
            ->whereHas('roleAssignments', fn ($query) => $query->where('tenant_id', $company->tenant_id)->where(function ($scope) use ($company): void { $scope->whereNull('company_id')->orWhere('company_id', $company->id); })->whereHas('role.permissions', fn ($permission) => $permission->where('name', 'organization.manage')))
            ->orderBy('name')->get(['id', 'name', 'email', 'status']);
        return response()->json(['data' => $users]);
    }

    public function staffCandidates(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompanyAccess($request, $company, $authorization, true);
        return response()->json(['data' => Employee::where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('status', 'active')->orderBy('legal_name')->get(['id', 'employee_number', 'legal_name', 'preferred_name', 'branch_id'])]);
    }

    public function store(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        $tenantId = $this->assertCompanyAccess($request, $company, $authorization, true);
        $data = $request->validate($this->rules($company));
        $this->validateManager($data['manager_user_id'] ?? null, $company, null, $authorization);
        $employeeIds = $this->validateAssignedStaff($data['assigned_staff'] ?? [], $company, $tenantId);
        $subscriptions->limit($tenantId, 'branches', Branch::where('tenant_id', $tenantId)->count());

        $branch = DB::transaction(function () use ($data, $employeeIds, $tenantId, $company, $request, $audit): Branch {
            $branch = Branch::create($this->branchAttributes($data, $tenantId, $company->id));
            if ($employeeIds !== []) Employee::where('tenant_id', $tenantId)->whereIn('id', $employeeIds)->update(['branch_id' => $branch->id]);
            $audit->record($request, 'branch.created', $tenantId, $branch, ['company_id' => $company->id]);
            return $branch->fresh(['manager:id,name,email,status']);
        });

        return response()->json(['data' => $this->payload($branch)], 201);
    }

    public function show(Request $request, Company $company, Branch $branch, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->assertBranchAccess($request, $company, $branch, $authorization, false);
        $branch->load(['manager:id,name,email,status']);
        $staff = Employee::where('tenant_id', $tenantId)->where('company_id', $company->id)->where('branch_id', $branch->id)->orderBy('legal_name')->get(['id', 'user_id', 'employee_number', 'legal_name', 'preferred_name', 'status', 'joined_on']);
        $departments = $branch->departments()->orderBy('name')->get(['id', 'name', 'code']);
        $warehouses = $branch->warehouses()->orderBy('name')->get(['id', 'name', 'code', 'is_active']);
        return response()->json(['data' => [
            'branch' => $this->payload($branch, $staff->where('status', 'active')->count()),
            'staff' => $staff,
            'departments' => $departments,
            'warehouses' => $warehouses,
            'activity' => $this->activityRows($tenantId, $branch),
        ]]);
    }

    public function updateNested(Request $request, Company $company, Branch $branch, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = $this->assertBranchAccess($request, $company, $branch, $authorization, true);
        $data = $request->validate($this->rules($company, $branch, false));
        $this->validateManager($data['manager_user_id'] ?? $branch->manager_user_id, $company, $branch, $authorization);
        $employeeIds = $this->validateAssignedStaff($data['assigned_staff'] ?? [], $company, $tenantId);

        DB::transaction(function () use ($data, $employeeIds, $tenantId, $company, $branch, $request, $audit): void {
            $branch->update($this->branchAttributes($data, $tenantId, $company->id, false));
            if (array_key_exists('assigned_staff', $data)) {
                Employee::where('tenant_id', $tenantId)->where('company_id', $company->id)->where('branch_id', $branch->id)->update(['branch_id' => null]);
                if ($employeeIds !== []) Employee::where('tenant_id', $tenantId)->whereIn('id', $employeeIds)->update(['branch_id' => $branch->id]);
            }
            $audit->record($request, 'branch.updated', $tenantId, $branch, ['company_id' => $company->id]);
        });

        return response()->json(['data' => $this->payload($branch->fresh(['manager:id,name,email,status']))]);
    }

    public function update(Request $request, Branch $branch, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $company = Company::where('tenant_id', app(TenantContext::class)->id())->whereKey($branch->company_id)->firstOrFail();
        if ($request->has('is_active') && !$request->has('status')) $request->merge(['status' => $request->boolean('is_active') ? 'active' : 'inactive']);
        return $this->updateNested($request, $company, $branch, $authorization, $audit);
    }

    public function status(Request $request, Company $company, Branch $branch, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = $this->assertBranchAccess($request, $company, $branch, $authorization, true);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'archived'])]])['status'];
        if ($branch->status === 'archived' && $status !== 'archived') throw ValidationException::withMessages(['status' => ['Archived branches cannot be reactivated.']]);
        if ($status === 'archived' && $this->hasOpenReferences($branch)) throw ValidationException::withMessages(['status' => ['This branch has open operational records and cannot be archived yet.']]);

        $branch->update(['status' => $status, 'is_active' => $status === 'active', 'archived_at' => $status === 'archived' ? now() : null]);
        $audit->record($request, 'branch.status.updated', $tenantId, $branch, ['company_id' => $company->id, 'status' => $status]);
        return response()->json(['data' => $this->payload($branch->fresh(['manager:id,name,email,status']))]);
    }

    public function activity(Request $request, Company $company, Branch $branch, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = $this->assertBranchAccess($request, $company, $branch, $authorization, false);
        return response()->json(['data' => $this->activityRows($tenantId, $branch)]);
    }

    private function rules(Company $company, ?Branch $branch = null, bool $create = true): array
    {
        return [
            'name' => [$create ? 'required' : 'sometimes', 'string', 'max:150'],
            'code' => [$create ? 'required' : 'sometimes', 'alpha_dash', 'max:40', Rule::unique('branches', 'code')->where(fn ($query) => $query->where('company_id', $company->id))->ignore($branch?->id)],
            'branch_type' => ['nullable', 'string', 'max:80'],
            'opening_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60', 'regex:/^[0-9+()\- .]+$/'],
            'alternative_phone' => ['nullable', 'string', 'max:60', 'regex:/^[0-9+()\- .]+$/'],
            'country' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'township' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'street_address' => ['nullable', 'string', 'max:255'],
            'manager_user_id' => ['nullable', 'uuid'],
            'assigned_staff' => ['sometimes', 'array', 'max:200'],
            'assigned_staff.*' => ['uuid'],
            'business_hours' => ['nullable', 'array'],
            'timezone' => ['nullable', 'timezone'],
            'document_number_prefix' => ['nullable', 'alpha_dash', 'max:40'],
            'status' => [$create ? 'nullable' : 'prohibited', Rule::in(['active', 'inactive'])],
            'tenant_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'is_active' => ['prohibited'],
            'archived_at' => ['prohibited'],
        ];
    }

    private function branchAttributes(array $data, string $tenantId, string $companyId, bool $create = true): array
    {
        $attributes = collect($data)->except(['assigned_staff', 'tenant_id', 'company_id', 'is_active', 'archived_at'])->all();
        if ($create) {
            $attributes['tenant_id'] = $tenantId;
            $attributes['company_id'] = $companyId;
            $attributes['status'] = $attributes['status'] ?? 'active';
            $attributes['is_active'] = $attributes['status'] === 'active';
        }
        return $attributes;
    }

    private function assertCompanyAccess(Request $request, Company $company, AuthorizationService $authorization, bool $manage): string
    {
        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId && $company->tenant_id === $tenantId, 404);
        abort_unless($company->is_active, 403, 'Company is inactive.');
        $permissions = $manage ? self::MANAGE_PERMISSIONS : self::READ_PERMISSIONS;
        if ($this->hasPermission($authorization, $request, $tenantId, $company->id, null, $permissions)) return $tenantId;
        if (!$manage) {
            foreach ($permissions as $permission) {
                $branchIds = $authorization->accessibleBranchIds($request->user(), $tenantId, $permission);
                if (is_array($branchIds) && Branch::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereIn('id', $branchIds)->exists()) return $tenantId;
            }
        }
        abort(403);
        return $tenantId;
    }

    private function assertBranchAccess(Request $request, Company $company, Branch $branch, AuthorizationService $authorization, bool $manage): string
    {
        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId && $company->tenant_id === $tenantId, 404);
        abort_unless($company->is_active, 403, 'Company is inactive.');
        abort_unless($branch->tenant_id === $tenantId && $branch->company_id === $company->id, 404);
        $permissions = $manage ? self::MANAGE_PERMISSIONS : self::READ_PERMISSIONS;
        abort_unless($this->hasPermission($authorization, $request, $tenantId, $company->id, $branch->id, $permissions), 403);
        return $tenantId;
    }

    private function hasPermission(AuthorizationService $authorization, Request $request, string $tenantId, string $companyId, ?string $branchId, array $permissions): bool
    {
        foreach ($permissions as $permission) if ($authorization->allows($request->user(), $tenantId, $permission, $companyId, $branchId)) return true;
        return false;
    }

    private function scopeToAuthorizedBranches(Builder $query, Request $request, Company $company, AuthorizationService $authorization): void
    {
        $tenantId = app(TenantContext::class)->id();
        $ids = [];
        foreach (self::READ_PERMISSIONS as $permission) {
            if ($authorization->allows($request->user(), $tenantId, $permission, $company->id)) return;
            $scoped = $authorization->accessibleBranchIds($request->user(), $tenantId, $permission);
            if ($scoped === null) return;
            $ids = array_merge($ids, $scoped);
        }
        if ($ids === []) $query->whereRaw('1 = 0');
        else $query->whereIn('id', array_values(array_unique($ids)));
    }

    private function validateManager(?string $managerId, Company $company, ?Branch $branch, AuthorizationService $authorization): void
    {
        if (!$managerId) return;
        $manager = User::whereKey($managerId)->where('status', 'active')->whereHas('tenantMemberships', fn ($query) => $query->where('tenant_id', $company->tenant_id)->where('status', 'active'))->first();
        if (!$manager) throw ValidationException::withMessages(['manager_user_id' => ['The selected manager is not an active member of this workspace.']]);
        $allowed = $authorization->allows($manager, $company->tenant_id, 'organization.manage', $company->id, $branch?->id);
        if (!$allowed) throw ValidationException::withMessages(['manager_user_id' => ['The selected manager does not have an authorized organization role for this company.']]);
    }

    private function validateAssignedStaff(array $employeeIds, Company $company, string $tenantId): array
    {
        if ($employeeIds === []) return [];
        $valid = Employee::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereIn('id', $employeeIds)->where('status', 'active')->pluck('id')->all();
        if (count($valid) !== count(array_unique($employeeIds))) throw ValidationException::withMessages(['assigned_staff' => ['All assigned staff must be active employees of this company.']]);
        return $valid;
    }

    private function hasOpenReferences(Branch $branch): bool
    {
        return SalesOrder::where('tenant_id', $branch->tenant_id)->where('company_id', $branch->company_id)->where('branch_id', $branch->id)->whereIn('state', ['draft', 'pending_approval', 'approved', 'partially_fulfilled'])->exists()
            || PurchaseOrder::where('tenant_id', $branch->tenant_id)->where('company_id', $branch->company_id)->where('branch_id', $branch->id)->whereIn('state', ['draft', 'pending_approval', 'approved', 'partially_received'])->exists()
            || ApprovalRequest::where('tenant_id', $branch->tenant_id)->where('company_id', $branch->company_id)->whereIn('state', ['pending', 'in_progress'])->exists();
    }

    private function activityRows(string $tenantId, Branch $branch)
    {
        return PlatformAuditLog::where('tenant_id', $tenantId)->where('auditable_id', $branch->id)->whereIn('action', ['branch.created', 'branch.updated', 'branch.status.updated'])->latest()->limit(20)->get(['id', 'action', 'created_at', 'metadata', 'actor_user_id']);
    }

    private function payload(Branch $branch, ?int $staffCount = null): array
    {
        $payload = $branch->toArray();
        $payload['manager'] = $branch->manager ? $branch->manager->only(['id', 'name', 'email', 'status']) : null;
        $payload['staff_count'] = $staffCount ?? ($branch->staff_count ?? $branch->employees()->where('status', 'active')->count());
        return $payload;
    }
}
