<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Company, Employee, Project, ProjectTask, ProjectTimesheet};
use App\Services\{AuthorizationService, OptionalModuleService};
use App\Support\TenantContext;
use Illuminate\Http\Request;

final class ProjectController
{
    public function index(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization);
        $query = Project::where('tenant_id', $tenant)->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))->with('tasks')->orderBy('name');
        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function store(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $tenant = app(TenantContext::class)->id(); $data = $request->validate(['company_id' => 'required|uuid', 'customer_id' => 'nullable|uuid', 'manager_id' => 'nullable|uuid', 'code' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:200', 'budget_amount' => 'nullable|numeric|min:0', 'starts_on' => 'nullable|date', 'ends_on' => 'nullable|date|after_or_equal:starts_on']);
        abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404); return response()->json(['data' => Project::create([...$data, 'tenant_id' => $tenant])], 201);
    }

    public function task(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $tenant = app(TenantContext::class)->id(); abort_unless($project->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $project->company_id), 403); $data = $request->validate(['milestone_id' => 'nullable|uuid', 'assignee_id' => 'nullable|uuid', 'title' => 'required|string|max:200', 'estimated_hours' => 'nullable|numeric|min:0']); return response()->json(['data' => ProjectTask::create([...$data, 'tenant_id' => $tenant, 'project_id' => $project->id])], 201);
    }

    public function timesheet(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $tenant = app(TenantContext::class)->id(); abort_unless($project->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'projects.time', $project->company_id), 403); $data = $request->validate(['task_id' => 'nullable|uuid', 'employee_id' => 'required|uuid', 'work_date' => 'required|date', 'hours' => 'required|numeric|gt:0', 'description' => 'nullable|string']); abort_unless(Employee::where('tenant_id', $tenant)->where('company_id', $project->company_id)->whereKey($data['employee_id'])->exists(), 404); return response()->json(['data' => ProjectTimesheet::create([...$data, 'tenant_id' => $tenant, 'project_id' => $project->id])], 201);
    }

    private function scope(Request $request, AuthorizationService $authorization): array
    {
        $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $company), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, 'projects.manage'); abort_unless($companies === null || $companies !== [], 403); return [$tenant, $companies];
    }
}
