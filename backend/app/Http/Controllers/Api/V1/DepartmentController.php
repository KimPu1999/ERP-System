<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DepartmentController
{
    private const READ_PERMISSIONS = ['organization.manage', 'company.manage', 'reports.view'];

    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $companyId = $request->query('company_id');
        $this->assertCompanyId($request, $authorization, $companyId, false);
        $query = Department::query()->where('tenant_id', $tenantId)
            ->with(['company:id,name', 'branch:id,name', 'parent:id,name', 'manager:id,name'])
            ->withCount(['employees as staff_count' => fn (Builder $employee) => $employee->where('status', 'active')]);
        $this->scopeCompanies($query, $request, $authorization, $tenantId, $companyId);
        if ($request->filled('search')) $query->where(fn (Builder $department) => $department->where('name', 'ilike', '%'.$request->string('search')->toString().'%')->orWhere('code', 'ilike', '%'.$request->string('search')->toString().'%'));
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('branch_id')) $query->where('branch_id', $request->string('branch_id')->toString());
        $sort = in_array($request->query('sort'), ['name', 'code', 'status', 'created_at'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max($request->integer('per_page', 25), 10), 100);
        $summaryQuery = clone $query;
        $summary = ['total' => (clone $summaryQuery)->count(), 'active' => (clone $summaryQuery)->where('status', 'active')->count(), 'inactive' => (clone $summaryQuery)->where('status', 'inactive')->count(), 'assigned_managers' => (clone $summaryQuery)->whereNotNull('manager_user_id')->distinct('manager_user_id')->count('manager_user_id'), 'assigned_members' => (int) Employee::where('tenant_id', $tenantId)->when($companyId, fn ($q) => $q->where('company_id', $companyId))->whereNotNull('department_id')->where('status', 'active')->count()];
        return response()->json(['data' => $query->orderBy($sort, $direction)->paginate($perPage)->withQueryString(), 'summary' => $summary]);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate($this->rules($tenantId));
        $company = $this->company($data['company_id'], $tenantId);
        $this->assertManage($request, $authorization, $company);
        $this->validateRelations($data, $company, $tenantId, $authorization, null);
        $department = DB::transaction(function () use ($data, $tenantId, $company, $request, $audit): Department {
            $department = Department::create([...$data, 'tenant_id' => $tenantId, 'status' => $data['status'] ?? 'active']);
            $audit->record($request, 'department.created', $tenantId, $department, ['company_id' => $company->id]);
            return $department;
        });
        return response()->json(['data' => $this->payload($department->fresh(['company:id,name', 'branch:id,name', 'parent:id,name', 'manager:id,name']))], 201);
    }

    public function show(Request $request, Department $department, AuthorizationService $authorization): JsonResponse
    {
        $this->assertDepartment($request, $department, $authorization, false);
        $department->load(['company:id,name', 'branch:id,name', 'parent:id,name', 'children:id,name,code,status,parent_department_id', 'manager:id,name']);
        return response()->json(['data' => [...$this->payload($department), 'staff_count' => $department->employees()->where('status', 'active')->count(), 'activity' => $this->activityRows($department)]]);
    }

    public function update(Request $request, Department $department, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $company = $this->company($department->company_id, $tenantId);
        $this->assertDepartment($request, $department, $authorization, true);
        $data = $request->validate($this->rules($tenantId, $department));
        $this->validateRelations($data, $company, $tenantId, $authorization, $department);
        $department->update(collect($data)->except(['tenant_id', 'company_id'])->all());
        $audit->record($request, 'department.updated', $department->tenant_id, $department, ['company_id' => $company->id]);
        return response()->json(['data' => $this->payload($department->fresh(['company:id,name', 'branch:id,name', 'parent:id,name', 'manager:id,name']))]);
    }

    public function status(Request $request, Department $department, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertDepartment($request, $department, $authorization, true);
        $status = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'archived'])]])['status'];
        if ($department->status === 'archived' && $status !== 'archived') throw ValidationException::withMessages(['status' => ['Archived departments cannot be reactivated.']]);
        $department->update(['status' => $status]);
        $audit->record($request, 'department.status.updated', $department->tenant_id, $department, ['company_id' => $department->company_id, 'status' => $status]);
        return response()->json(['data' => $this->payload($department->fresh(['company:id,name', 'branch:id,name', 'parent:id,name', 'manager:id,name']))]);
    }

    public function activity(Request $request, Department $department, AuthorizationService $authorization): JsonResponse
    {
        $this->assertDepartment($request, $department, $authorization, false);
        return response()->json(['data' => $this->activityRows($department)]);
    }

    private function rules(string $tenantId, ?Department $department = null): array
    {
        $companyId = $department?->company_id ?? request()->input('company_id');
        return ['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('departments', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($department?->id)], 'company_id' => [$department ? 'prohibited' : 'required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'parent_department_id' => ['nullable', 'uuid'], 'manager_user_id' => ['nullable', 'uuid'], 'description' => ['nullable', 'string', 'max:5000'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'archived'])], 'tenant_id' => ['prohibited']];
    }

    private function validateRelations(array $data, Company $company, string $tenantId, AuthorizationService $authorization, ?Department $department): void
    {
        if (!empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['branch_id'])->exists(), 422, 'Branch does not belong to this company.');
        if (!empty($data['parent_department_id'])) {
            abort_if($department && $data['parent_department_id'] === $department->id, 422, 'A department cannot be its own parent.');
            $parent = Department::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['parent_department_id'])->firstOrFail();
            $seen = array_filter([$department?->id]);
            while ($parent) {
                if (in_array($parent->id, $seen, true)) throw ValidationException::withMessages(['parent_department_id' => ['Circular department hierarchies are not allowed.']]);
                $seen[] = $parent->id;
                $parent = $parent->parent_department_id ? Department::find($parent->parent_department_id) : null;
            }
        }
        if (!empty($data['manager_user_id'])) {
            $manager = User::whereKey($data['manager_user_id'])->where('status', 'active')->whereHas('companyMemberships', fn ($query) => $query->where('company_id', $company->id)->where('status', 'active'))->first();
            abort_unless($manager && $authorization->allows($manager, $tenantId, 'organization.manage', $company->id), 422, 'The selected manager is not an authorized active member of this company.');
        }
    }

    private function assertCompanyId(Request $request, AuthorizationService $authorization, ?string $companyId, bool $manage): void
    {
        if (!$companyId) return;
        $company = $this->company($companyId, (string) app(TenantContext::class)->id());
        $this->assertManageOrRead($request, $authorization, $company, $manage);
    }

    private function assertDepartment(Request $request, Department $department, AuthorizationService $authorization, bool $manage): void
    {
        $company = $this->company($department->company_id, (string) app(TenantContext::class)->id());
        $this->assertManageOrRead($request, $authorization, $company, $manage);
    }

    private function assertManage(Request $request, AuthorizationService $authorization, Company $company): void { abort_unless($authorization->allows($request->user(), $company->tenant_id, 'organization.manage', $company->id), 403); }
    private function assertManageOrRead(Request $request, AuthorizationService $authorization, Company $company, bool $manage): void { $permissions = $manage ? ['organization.manage', 'company.manage'] : self::READ_PERMISSIONS; foreach ($permissions as $permission) if ($authorization->allows($request->user(), $company->tenant_id, $permission, $company->id)) return; abort(403); }
    private function company(string $companyId, string $tenantId): Company { return Company::where('tenant_id', $tenantId)->where('is_active', true)->whereKey($companyId)->firstOrFail(); }
    private function scopeCompanies(Builder $query, Request $request, AuthorizationService $authorization, string $tenantId, ?string $companyId): void { if ($companyId) { $query->where('company_id', $companyId); return; } $ids = []; foreach (self::READ_PERMISSIONS as $permission) { $accessible = $authorization->accessibleCompanyIds($request->user(), $tenantId, $permission); if ($accessible === null) return; $ids = array_merge($ids, $accessible); } $query->whereIn('company_id', array_values(array_unique($ids))); }
    private function activityRows(Department $department) { return PlatformAuditLog::where('tenant_id', $department->tenant_id)->where('auditable_id', $department->id)->latest()->limit(20)->get(['id', 'action', 'actor_user_id', 'metadata', 'created_at']); }
    private function payload(Department $department): array { return [...$department->toArray(), 'manager' => $department->manager?->only(['id', 'name']), 'parent' => $department->parent?->only(['id', 'name'])]; }
}
