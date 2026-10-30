<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Company, Employee, Project, ProjectExpense, ProjectMember, ProjectMilestone, ProjectTask, ProjectTimesheet};
use App\Services\{AuthorizationService, OptionalModuleService};
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProjectController
{
    public function dashboard(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization);
        $query = Project::query()->where('tenant_id', $tenant)->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')));
        $projects = $query->get(['id', 'state', 'ends_on']); $projectIds = $projects->pluck('id');
        $tasks = ProjectTask::where('tenant_id', $tenant)->whereIn('project_id', $projectIds)->get(['project_id', 'state']);
        $overdue = $projects->filter(fn (Project $project) => $project->ends_on && $project->ends_on->isPast() && ! in_array($project->state, ['completed', 'cancelled'], true))->count();
        return response()->json(['data' => ['projects' => ['total' => $projects->count(), 'active' => $projects->where('state', 'active')->count(), 'completed' => $projects->where('state', 'completed')->count(), 'overdue' => $overdue], 'tasks' => ['open' => $tasks->whereNotIn('state', ['done', 'completed'])->count(), 'total' => $tasks->count()], 'milestones' => ['pending' => ProjectMilestone::where('tenant_id', $tenant)->whereIn('project_id', $projectIds)->whereNotIn('state', ['completed', 'done'])->count()], 'status_distribution' => $projects->groupBy('state')->map->count()]]);
    }

    public function index(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization);
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'state' => ['nullable', Rule::in(['draft', 'planned', 'active', 'on_hold', 'completed', 'cancelled'])], 'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = Project::query()->where('tenant_id', $tenant)->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested->where('name', 'ilike', '%' . addcslashes($search, '%_') . '%')->orWhere('code', 'ilike', '%' . addcslashes($search, '%_') . '%')))->withCount(['tasks', 'milestones'])->with('manager:id,employee_number,legal_name,preferred_name')->orderBy('name');
        return response()->json(['data' => $query->paginate($data['per_page'] ?? 25)]);
    }

    public function show(Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, request(), $authorization); return response()->json(['data' => $project->load(['tasks.assignee:id,legal_name,preferred_name', 'milestones.tasks', 'members.employee:id,employee_number,legal_name,preferred_name', 'timesheets.employee:id,legal_name,preferred_name', 'expenses'])]);
    }

    public function store(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $tenant = (string) app(TenantContext::class)->id(); $data = $request->validate(['company_id' => ['required', 'uuid'], 'customer_id' => ['nullable', 'uuid'], 'manager_id' => ['nullable', 'uuid'], 'code' => ['required', 'alpha_dash', 'max:40'], 'name' => ['required', 'string', 'max:200'], 'state' => ['nullable', Rule::in(['draft', 'planned', 'active', 'on_hold', 'completed', 'cancelled'])], 'budget_amount' => ['nullable', 'numeric', 'min:0'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]); $this->assertCompany($request, $authorization, $tenant, $data['company_id']); if (! empty($data['manager_id'])) abort_unless(Employee::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->whereKey($data['manager_id'])->exists(), 404); abort_unless(! Project::where('company_id', $data['company_id'])->where('code', $data['code'])->exists(), 422, 'Project code already exists for this company.'); return response()->json(['data' => Project::create([...$data, 'tenant_id' => $tenant, 'state' => $data['state'] ?? 'draft'])], 201);
    }

    public function update(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['manager_id' => ['nullable', 'uuid'], 'code' => ['sometimes', 'alpha_dash', 'max:40'], 'name' => ['sometimes', 'string', 'max:200'], 'state' => ['sometimes', Rule::in(['draft', 'planned', 'active', 'on_hold', 'completed', 'cancelled'])], 'budget_amount' => ['sometimes', 'numeric', 'min:0'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]); if (isset($data['code']) && $data['code'] !== $project->code) abort_unless(! Project::where('company_id', $project->company_id)->where('code', $data['code'])->where('id', '!=', $project->id)->exists(), 422, 'Project code already exists for this company.'); if (array_key_exists('manager_id', $data) && $data['manager_id']) abort_unless(Employee::where('tenant_id', $project->tenant_id)->where('company_id', $project->company_id)->whereKey($data['manager_id'])->exists(), 404); if (isset($data['state']) && ! $this->validProjectTransition((string) $project->state, $data['state'])) abort(422, 'This project status transition is not allowed.'); $project->update($data); return response()->json(['data' => $project->fresh()->loadCount(['tasks', 'milestones'])]);
    }

    public function tasks(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization); $data = $request->validate(['project_id' => ['nullable', 'uuid'], 'state' => ['nullable', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'blocked'])], 'search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]); $query = ProjectTask::query()->where('project_tasks.tenant_id', $tenant)->whereHas('project', fn ($project) => $project->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies)))->when($data['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))->when($data['search'] ?? null, fn ($q, $search) => $q->where('title', 'ilike', '%' . addcslashes($search, '%_') . '%'))->with(['project:id,company_id,code,name', 'assignee:id,legal_name,preferred_name'])->orderByDesc('created_at'); return response()->json(['data' => $query->paginate($data['per_page'] ?? 50)]);
    }

    public function task(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['milestone_id' => ['nullable', 'uuid'], 'assignee_id' => ['nullable', 'uuid'], 'title' => ['required', 'string', 'max:200'], 'state' => ['nullable', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'blocked'])], 'estimated_hours' => ['nullable', 'numeric', 'min:0']]); $this->assertTaskReferences($data, $project); return response()->json(['data' => ProjectTask::create([...$data, 'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'state' => $data['state'] ?? 'todo'])], 201);
    }

    public function updateTask(Request $request, ProjectTask $task, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $project = $task->project()->firstOrFail(); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['milestone_id' => ['nullable', 'uuid'], 'assignee_id' => ['nullable', 'uuid'], 'title' => ['sometimes', 'string', 'max:200'], 'state' => ['sometimes', Rule::in(['todo', 'in_progress', 'in_review', 'done', 'blocked'])], 'estimated_hours' => ['sometimes', 'numeric', 'min:0']]); $this->assertTaskReferences($data, $project); $task->update($data); return response()->json(['data' => $task->fresh()]);
    }

    public function milestones(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization); $data = $request->validate(['project_id' => ['nullable', 'uuid'], 'state' => ['nullable', 'string', 'max:30'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]); $query = ProjectMilestone::query()->where('tenant_id', $tenant)->whereHas('project', fn ($project) => $project->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies)))->when($data['project_id'] ?? null, fn ($q, $id) => $q->where('project_id', $id))->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))->withCount('tasks')->orderBy('due_on'); return response()->json(['data' => $query->paginate($data['per_page'] ?? 50)]);
    }

    public function milestone(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'due_on' => ['nullable', 'date'], 'state' => ['nullable', 'string', 'max:30']]); return response()->json(['data' => ProjectMilestone::create([...$data, 'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'state' => $data['state'] ?? 'open'])], 201);
    }

    public function timesheets(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization); $query = ProjectTimesheet::query()->where('project_timesheets.tenant_id', $tenant)->whereHas('project', fn ($project) => $project->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies)))->with(['project:id,company_id,code,name', 'employee:id,legal_name,preferred_name', 'task:id,title'])->orderByDesc('work_date'); return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 50), 1), 100))]);
    }

    public function timesheet(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $tenant = $project->tenant_id; abort_unless($authorization->allows($request->user(), $tenant, 'projects.time', $project->company_id), 403); $data = $request->validate(['task_id' => ['nullable', 'uuid'], 'employee_id' => ['required', 'uuid'], 'work_date' => ['required', 'date'], 'hours' => ['required', 'numeric', 'gt:0', 'max:24'], 'description' => ['nullable', 'string', 'max:2000']]); abort_unless(Employee::where('tenant_id', $tenant)->where('company_id', $project->company_id)->whereKey($data['employee_id'])->exists(), 404); if (! empty($data['task_id'])) abort_unless(ProjectTask::where('tenant_id', $tenant)->where('project_id', $project->id)->whereKey($data['task_id'])->exists(), 404); return response()->json(['data' => ProjectTimesheet::create([...$data, 'tenant_id' => $tenant, 'project_id' => $project->id, 'state' => 'draft'])], 201);
    }

    public function timesheetTransition(Request $request, ProjectTimesheet $timesheet, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $project = $timesheet->project()->firstOrFail(); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['state' => ['required', Rule::in(['submitted', 'approved', 'rejected'])]]); abort_unless($timesheet->state !== 'approved', 422, 'Approved timesheets require an authorized correction workflow.'); $timesheet->update(['state' => $data['state']]); return response()->json(['data' => $timesheet->fresh()]);
    }

    public function members(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); return response()->json(['data' => $project->members()->with('employee:id,employee_number,legal_name,preferred_name,status')->get()]);
    }

    public function member(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['employee_id' => ['required', 'uuid'], 'role' => ['nullable', 'string', 'max:60'], 'hourly_rate' => ['nullable', 'numeric', 'min:0']]); abort_unless(Employee::where('tenant_id', $project->tenant_id)->where('company_id', $project->company_id)->whereKey($data['employee_id'])->exists(), 404); return response()->json(['data' => ProjectMember::firstOrCreate(['tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'employee_id' => $data['employee_id']], ['role' => $data['role'] ?? 'team_member', 'hourly_rate' => $data['hourly_rate'] ?? 0])], 201);
    }

    public function expenses(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); [$tenant, $companies] = $this->scope($request, $authorization); $query = ProjectExpense::query()->where('project_expenses.tenant_id', $tenant)->whereHas('project', fn ($project) => $project->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies)))->with('project:id,company_id,code,name')->orderByDesc('expense_date'); return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 50), 1), 100))]);
    }

    public function expense(Request $request, Project $project, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('projects'); $this->authorizeProject($project, $request, $authorization); $data = $request->validate(['employee_id' => ['nullable', 'uuid'], 'amount' => ['required', 'numeric', 'gt:0'], 'currency_code' => ['required', 'string', 'size:3'], 'expense_date' => ['required', 'date'], 'description' => ['nullable', 'string', 'max:2000']]); if (! empty($data['employee_id'])) abort_unless(Employee::where('tenant_id', $project->tenant_id)->where('company_id', $project->company_id)->whereKey($data['employee_id'])->exists(), 404); return response()->json(['data' => ProjectExpense::create([...$data, 'tenant_id' => $project->tenant_id, 'project_id' => $project->id, 'state' => 'draft'])], 201);
    }

    private function scope(Request $request, AuthorizationService $authorization): array { $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $company), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, 'projects.manage'); abort_unless($companies === null || $companies !== [], 403); return [$tenant, $companies]; }
    private function authorizeProject(Project $project, Request $request, AuthorizationService $authorization): void { $tenant = (string) app(TenantContext::class)->id(); abort_unless($project->tenant_id === $tenant, 404); abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $project->company_id), 403); }
    private function assertCompany(Request $request, AuthorizationService $authorization, string $tenant, string $company): void { abort_unless($authorization->allows($request->user(), $tenant, 'projects.manage', $company), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($company)->exists(), 404); }
    private function assertTaskReferences(array $data, Project $project): void { if (! empty($data['milestone_id'])) abort_unless(ProjectMilestone::where('tenant_id', $project->tenant_id)->where('project_id', $project->id)->whereKey($data['milestone_id'])->exists(), 404); if (! empty($data['assignee_id'])) abort_unless(Employee::where('tenant_id', $project->tenant_id)->where('company_id', $project->company_id)->whereKey($data['assignee_id'])->exists(), 404); }
    private function validProjectTransition(string $from, string $to): bool { $allowed = ['draft' => ['planned', 'cancelled'], 'planned' => ['active', 'on_hold', 'cancelled'], 'active' => ['on_hold', 'completed', 'cancelled'], 'on_hold' => ['active', 'cancelled'], 'completed' => [], 'cancelled' => []]; return $from === $to || in_array($to, $allowed[$from] ?? [], true); }
}
