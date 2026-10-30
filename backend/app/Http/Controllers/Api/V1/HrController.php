<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Branch, Company, Department, Employee, JobTitle, LeaveRequest, Payslip, PayrollRun, AttendanceRecord};
use App\Services\{AuditLogger, AuthorizationService, HrPayrollService};
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class HrController
{
    public function employees(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate([
            'company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:30'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'sort' => ['nullable', Rule::in(['legal_name', 'employee_number', 'joined_on', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $allowedCompanyIds = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'hr.manage');
        abort_unless($allowedCompanyIds === null || $allowedCompanyIds !== [], 403);
        $query = Employee::query()->where('employees.tenant_id', $tenantId)->with(['company:id,name', 'branch:id,name', 'department:id,name', 'jobTitle:id,name']);
        if ($allowedCompanyIds !== null) $query->whereIn('employees.company_id', $allowedCompanyIds);
        if (! empty($data['company_id'])) { $this->assertCompanyScope($data['company_id'], $tenantId, $allowedCompanyIds); $query->where('employees.company_id', $data['company_id']); }
        $query->when($data['branch_id'] ?? null, fn ($q, $value) => $q->where('employees.branch_id', $value));
        $query->when($data['department_id'] ?? null, fn ($q, $value) => $q->where('employees.department_id', $value));
        $query->when($data['status'] ?? null, fn ($q, $value) => $q->where('employees.status', $value));
        $query->when($data['search'] ?? null, function ($q, $value): void {
            $term = '%' . addcslashes($value, '%_') . '%';
            $q->where(fn ($inner) => $inner->where('employees.legal_name', 'ilike', $term)->orWhere('employees.preferred_name', 'ilike', $term)->orWhere('employees.employee_number', 'ilike', $term));
        });
        $sort = $data['sort'] ?? 'legal_name';
        $direction = $data['direction'] ?? 'asc';
        $paginator = (clone $query)->orderBy('employees.' . $sort, $direction)->paginate($data['per_page'] ?? 25);
        return response()->json(['data' => $paginator, 'summary' => [
            'total' => (clone $query)->count(), 'active' => (clone $query)->where('employees.status', 'active')->count(),
            'inactive' => (clone $query)->where('employees.status', 'inactive')->count(), 'ended' => (clone $query)->whereIn('employees.status', ['ended', 'terminated'])->count(),
        ]]);
    }

    public function storeEmployee(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate($this->employeeRules($tenantId));
        $allowed = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'hr.manage');
        $company = $this->assertCompanyScope($data['company_id'], $tenantId, $allowed);
        $this->validateReferences($data, $company, $tenantId);
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage', $company->id), 403);
        $employee = Employee::create([...$data, 'tenant_id' => $tenantId]);
        $audit->record($request, 'hr.employee.created', $tenantId, $employee);
        return response()->json(['data' => $employee->load(['company:id,name', 'branch:id,name', 'department:id,name', 'jobTitle:id,name'])], 201);
    }

    public function updateEmployee(Request $request, Employee $employee, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($employee->tenant_id === $tenantId, 404);
        $allowed = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'hr.manage');
        $this->assertCompanyScope($employee->company_id, $tenantId, $allowed);
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage', $employee->company_id), 403);
        $data = $request->validate($this->employeeRules($tenantId, $employee->id, true));
        $company = Company::where('tenant_id', $tenantId)->whereKey($data['company_id'] ?? $employee->company_id)->firstOrFail();
        $this->assertCompanyScope($company->id, $tenantId, $allowed);
        $this->validateReferences($data, $company, $tenantId);
        $employee->update($data);
        $audit->record($request, 'hr.employee.updated', $tenantId, $employee);
        return response()->json(['data' => $employee->fresh()->load(['company:id,name', 'branch:id,name', 'department:id,name', 'jobTitle:id,name'])]);
    }

    public function status(Request $request, Employee $employee, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($employee->tenant_id === $tenantId, 404);
        $allowed = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'hr.manage');
        $this->assertCompanyScope($employee->company_id, $tenantId, $allowed);
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage', $employee->company_id), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'ended', 'terminated'])]]);
        $employee->update(['status' => $data['status'], 'ended_on' => in_array($data['status'], ['ended', 'terminated'], true) ? ($employee->ended_on ?? now()->toDateString()) : null]);
        $audit->record($request, 'hr.employee.status_changed', $tenantId, $employee, ['status' => $data['status']]);
        return response()->json(['data' => $employee->fresh()->load(['company:id,name', 'branch:id,name', 'department:id,name', 'jobTitle:id,name'])]);
    }

    public function jobTitles(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage'), 403);
        return response()->json(['data' => JobTitle::where('tenant_id', $tenantId)->orderBy('name')->paginate(50)]);
    }

    public function leave(Request $request, HrPayrollService $hr, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate(['employee_id' => 'required|uuid', 'leave_policy_id' => 'required|uuid', 'starts_on' => 'required|date', 'ends_on' => 'required|date|after_or_equal:starts_on', 'days' => 'required|numeric|gt:0', 'reason' => 'nullable|string']);
        $employee = Employee::where('tenant_id', $tenantId)->whereKey($data['employee_id'])->firstOrFail();
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage', $employee->company_id), 403);
        return response()->json(['data' => $hr->requestLeave([...$data, 'tenant_id' => $tenantId])], 201);
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest, HrPayrollService $hr, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($leaveRequest->tenant_id === $tenantId, 404);
        $employee = Employee::whereKey($leaveRequest->employee_id)->firstOrFail();
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.approve', $employee->company_id), 403);
        return response()->json(['data' => $hr->approveLeave($leaveRequest, $request->user()->id)]);
    }

    public function attendance(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate(['employee_id' => 'required|uuid', 'work_date' => 'required|date', 'clock_in' => 'nullable|date', 'clock_out' => 'nullable|date', 'worked_hours' => 'nullable|numeric|min:0']);
        $employee = Employee::where('tenant_id', $tenantId)->whereKey($data['employee_id'])->firstOrFail();
        abort_unless($authorization->allows($request->user(), $tenantId, 'hr.manage', $employee->company_id), 403);
        return response()->json(['data' => AttendanceRecord::updateOrCreate(['employee_id' => $employee->id, 'work_date' => $data['work_date']], ['tenant_id' => $tenantId, 'clock_in' => $data['clock_in'] ?? null, 'clock_out' => $data['clock_out'] ?? null, 'worked_hours' => $data['worked_hours'] ?? 0, 'state' => 'closed'])]);
    }

    public function payroll(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100']]);
        $allowed = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'payroll.manage');
        abort_unless($allowed === null || $allowed !== [], 403);
        $query = PayrollRun::where('tenant_id', $tenantId)->with('items');
        if ($allowed !== null) $query->whereIn('company_id', $allowed);
        if (! empty($data['company_id'])) { $this->assertCompanyScope($data['company_id'], $tenantId, $allowed); $query->where('company_id', $data['company_id']); }
        $paginator = (clone $query)->latest()->paginate($data['per_page'] ?? 25);
        return response()->json(['data' => $paginator, 'summary' => ['total' => (clone $query)->count(), 'finalized' => (clone $query)->where('state', 'finalized')->count(), 'pending' => (clone $query)->whereIn('state', ['draft', 'review', 'approved'])->count()]]);
    }

    public function createPayroll(Request $request, HrPayrollService $hr, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        $data = $request->validate(['company_id' => 'required|uuid', 'name' => 'required|string|max:150', 'period_starts_on' => 'required|date', 'period_ends_on' => 'required|date|after_or_equal:period_starts_on', 'idempotency_key' => 'required|string|max:100']);
        $allowed = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'payroll.manage');
        $this->assertCompanyScope($data['company_id'], $tenantId, $allowed);
        abort_unless($authorization->allows($request->user(), $tenantId, 'payroll.manage', $data['company_id']), 403);
        return response()->json(['data' => $hr->createPayroll([...$data, 'tenant_id' => $tenantId, 'created_by' => $request->user()->id])], 201);
    }

    public function transition(Request $request, PayrollRun $run, HrPayrollService $hr, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($run->tenant_id === $tenantId, 404);
        $data = $request->validate(['state' => 'required|in:review,approved,finalized']);
        $permission = $data['state'] === 'approved' ? 'payroll.approve' : 'payroll.manage';
        abort_unless($authorization->allows($request->user(), $tenantId, $permission, $run->company_id), 403);
        $result = $hr->transition($run, $data['state'], $request->user()->id);
        if ($data['state'] === 'finalized') event(new \App\Events\PayrollFinalizedForAccounting($tenantId, $run->id, $run->company_id));
        return response()->json(['data' => $result]);
    }

    public function payslip(Request $request, Payslip $payslip, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($payslip->tenant_id === $tenantId, 404);
        $item = $payslip->payrollItem()->with('employee')->firstOrFail();
        abort_unless($authorization->allows($request->user(), $tenantId, 'payroll.view', $item->employee->company_id) || $item->employee->user_id === $request->user()->id, 403);
        return response()->json(['data' => $payslip->load('payrollItem')]);
    }

    private function employeeRules(string $tenantId, ?string $employeeId = null, bool $update = false): array
    {
        return [
            'company_id' => [$update ? 'sometimes' : 'required', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'department_id' => ['nullable', 'uuid'], 'job_title_id' => ['nullable', 'uuid'], 'user_id' => ['nullable', 'uuid'],
            'employee_number' => [$update ? 'sometimes' : 'required', 'alpha_dash', 'max:50', Rule::unique('employees', 'employee_number')->where(fn ($q) => $q->where('tenant_id', $tenantId))->ignore($employeeId)],
            'legal_name' => [$update ? 'sometimes' : 'required', 'string', 'max:200'], 'preferred_name' => ['nullable', 'string', 'max:200'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'], 'date_of_birth' => ['nullable', 'date'], 'joined_on' => [$update ? 'sometimes' : 'required', 'date'],
        ];
    }

    private function validateReferences(array $data, Company $company, string $tenantId): void
    {
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['branch_id'])->exists(), 422);
        if (! empty($data['department_id'])) abort_unless(Department::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['department_id'])->exists(), 422);
        if (! empty($data['job_title_id'])) abort_unless(JobTitle::where('tenant_id', $tenantId)->where('company_id', $company->id)->whereKey($data['job_title_id'])->exists(), 422);
    }

    private function assertCompanyScope(string $companyId, string $tenantId, ?array $allowed): Company
    {
        abort_if($allowed !== null && ! in_array($companyId, $allowed, true), 403);
        return Company::where('tenant_id', $tenantId)->whereKey($companyId)->firstOrFail();
    }
}
