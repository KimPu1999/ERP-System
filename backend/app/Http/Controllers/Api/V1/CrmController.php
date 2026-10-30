<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Branch, Company, CrmActivity, CrmLeadConversion, CrmOpportunityStageHistory, Customer, CustomerAddress, CustomerContact, Lead, Opportunity};
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CrmController
{
    private const STAGES = ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];

    private function tenant(): string { return app(TenantContext::class)->id(); }
    private function can(Request $request, string $permission, ?string $companyId = null, ?string $branchId = null): void
    {
        $allowed = app(AuthorizationService::class)->allows($request->user(), $this->tenant(), $permission, $companyId, $branchId)
            || ($permission === 'reports.view' && app(AuthorizationService::class)->allows($request->user(), $this->tenant(), 'sales.manage', $companyId, $branchId));
        abort_unless($allowed, 403);
    }
    private function companyScope(Request $request, string $companyId, ?string $branchId = null): void
    {
        abort_unless(Company::where('tenant_id', $this->tenant())->whereKey($companyId)->where('is_active', true)->exists(), 422, 'Company is not available.');
        if ($branchId) abort_unless(Branch::where('tenant_id', $this->tenant())->where('company_id', $companyId)->whereKey($branchId)->where('is_active', true)->exists(), 422, 'Branch is not available for this company.');
        $this->can($request, 'reports.view', $companyId, $branchId);
    }
    private function scope(Builder $query, Request $request, string $companyColumn = 'company_id'): Builder
    {
        $query->where('tenant_id', $this->tenant());
        if ($request->filled('company_id')) { $this->companyScope($request, $request->string('company_id')->toString(), $request->input('branch_id')); $query->where($companyColumn, $request->string('company_id')->toString()); }
        if ($request->filled('branch_id')) $query->where('branch_id', $request->string('branch_id')->toString());
        return $query;
    }
    private function page(Request $request): int { return min(max($request->integer('per_page', 25), 10), 100); }

    public function customers(Request $request)
    {
        $this->can($request, 'reports.view');
        $query = $this->scope(Customer::query()->with(['branch', 'assignedSalesperson']), $request);
        if ($request->filled('search')) { $s = $request->string('search')->toString(); $query->where(fn ($q) => $q->where('name','ilike',"%{$s}%")->orWhere('code','ilike',"%{$s}%")->orWhere('email','ilike',"%{$s}%")->orWhere('phone','ilike',"%{$s}%")); }
        foreach (['status', 'customer_type', 'assigned_salesperson_id'] as $filter) if ($request->filled($filter)) $query->where($filter, $request->input($filter));
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date('date_from'));
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date('date_to'));
        $sort = in_array($request->input('sort'), ['name','code','created_at','status'], true) ? $request->input('sort') : 'name';
        return response()->json(['data' => $query->orderBy($sort, $request->input('direction') === 'desc' ? 'desc' : 'asc')->paginate($this->page($request))]);
    }

    public function customer(Request $request, Customer $customer)
    {
        abort_unless($customer->tenant_id === $this->tenant(), 404); $this->can($request, 'reports.view', $customer->company_id, $customer->branch_id);
        return response()->json(['data' => $customer->load(['contacts','addresses','branch','assignedSalesperson','leads','opportunities'])]);
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate(['company_id'=>'required|uuid','branch_id'=>'nullable|uuid','name'=>'required|string|max:200','customer_type'=>['nullable',Rule::in(['individual','business'])],'email'=>'nullable|email|max:200','phone'=>'nullable|string|max:50','customer_code'=>'nullable|string|max:50','billing_address'=>'nullable|string','shipping_address'=>'nullable|string','status'=>['nullable',Rule::in(['active','inactive','archived'])]]);
        $this->companyScope($request, $data['company_id'], $data['branch_id'] ?? null); $this->can($request, 'sales.manage', $data['company_id'], $data['branch_id'] ?? null);
        $code = $data['customer_code'] ?? 'CUS-'.strtoupper(substr(str_replace('-', '', (string) str()->uuid()), 0, 10)); unset($data['customer_code']);
        abort_if(Customer::where('tenant_id',$this->tenant())->where('code',$code)->exists(), 422, 'Customer code already exists.');
        return response()->json(['data'=>Customer::create([...$data,'tenant_id'=>$this->tenant(),'code'=>$code,'customer_type'=>$data['customer_type'] ?? 'business'])],201);
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        abort_unless($customer->tenant_id === $this->tenant(), 404); $this->can($request, 'sales.manage', $customer->company_id, $customer->branch_id);
        $data = $request->validate(['name'=>'sometimes|required|string|max:200','email'=>'nullable|email|max:200','phone'=>'nullable|string|max:50','customer_type'=>[Rule::in(['individual','business'])],'status'=>[Rule::in(['active','inactive','archived'])],'company_id'=>'sometimes|uuid','branch_id'=>'nullable|uuid','notes'=>'nullable|string']);
        if (isset($data['company_id'])) $this->companyScope($request, $data['company_id'], $data['branch_id'] ?? $customer->branch_id);
        $customer->update($data); return response()->json(['data'=>$customer->fresh(['contacts','addresses'])]);
    }

    public function contact(Request $request, Customer $customer)
    {
        abort_unless($customer->tenant_id === $this->tenant(), 404); $this->can($request, 'sales.manage', $customer->company_id, $customer->branch_id);
        $data = $request->validate(['name'=>'required|string|max:200','email'=>'nullable|email','phone'=>'nullable|string|max:50','role'=>'nullable|string|max:100','department'=>'nullable|string|max:100','preferred_communication'=>'nullable|string|max:30','is_primary'=>'boolean']);
        if (($data['is_primary'] ?? false) === true) $customer->contacts()->update(['is_primary'=>false]);
        return response()->json(['data'=>CustomerContact::create([...$data,'tenant_id'=>$this->tenant(),'customer_id'=>$customer->id])],201);
    }

    public function address(Request $request, Customer $customer)
    {
        abort_unless($customer->tenant_id === $this->tenant(), 404); $this->can($request, 'sales.manage', $customer->company_id, $customer->branch_id);
        $data = $request->validate(['label'=>'required|string|max:100','address_type'=>['required',Rule::in(['billing','shipping','other'])],'country'=>'nullable|string|max:100','region'=>'nullable|string|max:100','city'=>'nullable|string|max:100','township'=>'nullable|string|max:100','postal_code'=>'nullable|string|max:30','street_address'=>'nullable|string','is_default_billing'=>'boolean','is_default_shipping'=>'boolean']);
        if (($data['is_default_billing'] ?? false) === true) $customer->addresses()->update(['is_default_billing'=>false]);
        if (($data['is_default_shipping'] ?? false) === true) $customer->addresses()->update(['is_default_shipping'=>false]);
        return response()->json(['data'=>CustomerAddress::create([...$data,'tenant_id'=>$this->tenant(),'customer_id'=>$customer->id])],201);
    }

    public function leads(Request $request)
    {
        $this->can($request, 'reports.view'); $query = $this->scope(Lead::query()->with(['customer','owner','branch']), $request);
        if ($request->filled('search')) { $s=$request->string('search')->toString(); $query->where(fn($q)=>$q->where('name','ilike',"%{$s}%")->orWhere('email','ilike',"%{$s}%")->orWhere('company','ilike',"%{$s}%")); }
        foreach (['state','lead_source','priority','owner_id'] as $f) if ($request->filled($f)) $query->where($f,$request->input($f));
        return response()->json(['data'=>$query->latest()->paginate($this->page($request))]);
    }

    public function storeLead(Request $request)
    {
        $data=$request->validate(['company_id'=>'required|uuid','branch_id'=>'nullable|uuid','name'=>'required|string|max:200','contact_name'=>'nullable|string|max:200','email'=>'nullable|email','phone'=>'nullable|string|max:50','company'=>'nullable|string|max:200','lead_source'=>'nullable|string|max:100','priority'=>['nullable',Rule::in(['low','normal','high'])],'state'=>['nullable',Rule::in(['new','contacted','qualified','nurturing','disqualified'])],'estimated_value'=>'nullable|numeric|min:0','currency'=>'nullable|string|max:10','owner_id'=>'nullable|uuid','description'=>'nullable|string','notes'=>'nullable|string']);
        $this->companyScope($request,$data['company_id'],$data['branch_id']??null); $this->can($request,'sales.manage',$data['company_id'],$data['branch_id']??null); return response()->json(['data'=>Lead::create([...$data,'tenant_id'=>$this->tenant(),'state'=>$data['state']??'new'])],201);
    }

    public function updateLead(Request $request, Lead $lead)
    {
        abort_unless($lead->tenant_id===$this->tenant(),404); $this->can($request,'sales.manage',$lead->company_id,$lead->branch_id); $data=$request->validate(['name'=>'sometimes|required|string|max:200','state'=>['sometimes',Rule::in(['new','contacted','qualified','nurturing','disqualified'])],'owner_id'=>'nullable|uuid','priority'=>[Rule::in(['low','normal','high'])],'description'=>'nullable|string','notes'=>'nullable|string']); $lead->update($data); return response()->json(['data'=>$lead->fresh()]);
    }

    public function convertLead(Request $request, Lead $lead)
    {
        abort_unless($lead->tenant_id===$this->tenant(),404); $this->can($request,'sales.manage',$lead->company_id,$lead->branch_id); $data=$request->validate(['customer_id'=>'nullable|uuid','create_opportunity'=>'boolean','opportunity_name'=>'nullable|string|max:200','idempotency_key'=>'nullable|string|max:100']);
        $result=DB::transaction(function() use ($lead,$data,$request){$locked=Lead::whereKey($lead->id)->lockForUpdate()->firstOrFail(); if($locked->conversion){return $locked->conversion->load('customer','opportunity');} $customer=$data['customer_id']?Customer::where('tenant_id',$this->tenant())->whereKey($data['customer_id'])->firstOrFail():Customer::create(['tenant_id'=>$this->tenant(),'company_id'=>$locked->company_id,'branch_id'=>$locked->branch_id,'name'=>$locked->company ?: $locked->name,'code'=>'CUS-'.strtoupper(substr(str_replace('-','',(string)str()->uuid()),0,10)),'email'=>$locked->email,'phone'=>$locked->phone,'status'=>'active','customer_type'=>'business']); if($customer->company_id!==$locked->company_id) abort(422,'Customer is outside the lead company.'); $opportunity=null; if($data['create_opportunity']??false){$opportunity=Opportunity::create(['tenant_id'=>$this->tenant(),'company_id'=>$locked->company_id,'branch_id'=>$locked->branch_id,'customer_id'=>$customer->id,'owner_id'=>$locked->owner_id,'name'=>$data['opportunity_name']??$locked->name,'state'=>'new','stage'=>'new','estimated_value'=>$locked->estimated_value,'currency'=>$locked->currency]);} $locked->update(['customer_id'=>$customer->id,'state'=>'converted']); return CrmLeadConversion::create(['tenant_id'=>$this->tenant(),'lead_id'=>$locked->id,'customer_id'=>$customer->id,'opportunity_id'=>$opportunity?->id,'converted_by'=>$request->user()->id,'idempotency_key'=>$data['idempotency_key']??null,'converted_at'=>now()])->load('customer','opportunity');});
        return response()->json(['data'=>$result]);
    }

    public function opportunities(Request $request)
    {
        $this->can($request,'reports.view'); $query=$this->scope(Opportunity::query()->with(['customer','owner','branch']),$request); if($request->filled('search')){$s=$request->string('search')->toString();$query->where('name','ilike',"%{$s}%");} foreach(['stage','state','owner_id','customer_id'] as $f)if($request->filled($f))$query->where($f,$request->input($f)); return response()->json(['data'=>$query->latest()->paginate($this->page($request))]);
    }
    public function storeOpportunity(Request $request)
    {
        $data=$request->validate(['company_id'=>'required|uuid','branch_id'=>'nullable|uuid','customer_id'=>'nullable|uuid','name'=>'required|string|max:200','stage'=>['nullable',Rule::in(self::STAGES)],'estimated_value'=>'nullable|numeric|min:0','probability'=>'nullable|numeric|min:0|max:100','currency'=>'nullable|string|max:10','owner_id'=>'nullable|uuid','expected_close_at'=>'nullable|date','priority'=>[Rule::in(['low','normal','high'])],'description'=>'nullable|string','notes'=>'nullable|string']); $this->companyScope($request,$data['company_id'],$data['branch_id']??null);$this->can($request,'sales.manage',$data['company_id'],$data['branch_id']??null); $data['stage']=$data['stage']??'new'; return response()->json(['data'=>Opportunity::create([...$data,'state'=>$data['stage'],'tenant_id'=>$this->tenant()])],201);
    }
    public function moveOpportunity(Request $request, Opportunity $opportunity)
    {
        abort_unless($opportunity->tenant_id===$this->tenant(),404);$this->can($request,'sales.manage',$opportunity->company_id,$opportunity->branch_id);$data=$request->validate(['stage'=>['required',Rule::in(self::STAGES)],'reason'=>'nullable|string|max:1000']);$updated=DB::transaction(function()use($opportunity,$data,$request){$o=Opportunity::whereKey($opportunity->id)->lockForUpdate()->firstOrFail();$from=$o->stage ?: $o->state;$o->update(['stage'=>$data['stage'],'state'=>$data['stage'],'lost_reason'=>$data['stage']==='lost'?($data['reason']??null):null]);CrmOpportunityStageHistory::create(['tenant_id'=>$this->tenant(),'opportunity_id'=>$o->id,'from_stage'=>$from,'to_stage'=>$data['stage'],'changed_by'=>$request->user()->id,'reason'=>$data['reason']??null]);return $o->fresh();});return response()->json(['data'=>$updated]);
    }

    public function activities(Request $request)
    {
        $this->can($request,'reports.view');$query=$this->scope(CrmActivity::query()->with(['customer','lead','opportunity','assignee']),$request);if($request->filled('status'))$query->where('status',$request->input('status'));if($request->filled('assigned_user_id'))$query->where('assigned_user_id',$request->input('assigned_user_id'));if($request->boolean('overdue'))$query->where('due_at','<',now())->where('status','open');return response()->json(['data'=>$query->orderByRaw("case when status = 'open' then 0 else 1 end")->orderBy('due_at')->paginate($this->page($request))]);
    }
    public function storeActivity(Request $request)
    {
        $data=$request->validate(['company_id'=>'required|uuid','branch_id'=>'nullable|uuid','customer_id'=>'nullable|uuid','lead_id'=>'nullable|uuid','opportunity_id'=>'nullable|uuid','assigned_user_id'=>'nullable|uuid','activity_type'=>['required',Rule::in(['call','meeting','email','task','customer_follow_up','internal_note'])],'subject'=>'required|string|max:200','due_at'=>'nullable|date','priority'=>[Rule::in(['low','normal','high'])],'description'=>'nullable|string']);$this->companyScope($request,$data['company_id'],$data['branch_id']??null);$this->can($request,'sales.manage',$data['company_id'],$data['branch_id']??null);return response()->json(['data'=>CrmActivity::create([...$data,'tenant_id'=>$this->tenant(),'created_by'=>$request->user()->id])],201);
    }
    public function completeActivity(Request $request, CrmActivity $activity)
    {
        abort_unless($activity->tenant_id===$this->tenant(),404);$this->can($request,'sales.manage',$activity->company_id,$activity->branch_id);$data=$request->validate(['outcome'=>'nullable|string']);$activity->update(['status'=>'completed','outcome'=>$data['outcome']??null,'completed_at'=>now()]);return response()->json(['data'=>$activity->fresh()]);
    }

    public function pipeline(Request $request)
    {
        $this->can($request,'reports.view');$query=$this->scope(Opportunity::query()->with(['customer','owner']),$request);$items=$query->get();$stages=collect(self::STAGES)->map(fn($stage)=>['stage'=>$stage,'count'=>$items->where('stage',$stage)->count(),'total'=>$items->where('stage',$stage)->sum(fn($o)=>(float)$o->estimated_value),'items'=>$items->where('stage',$stage)->values()])->values();return response()->json(['data'=>['stages'=>$stages,'currency'=>$request->input('currency')]]);
    }

    public function dashboard(Request $request)
    {
        $this->can($request,'reports.view');$customers=$this->scope(Customer::query(),$request);$leads=$this->scope(Lead::query(),$request);$opps=$this->scope(Opportunity::query(),$request);$from=$request->date('date_from')?->startOfDay() ?? now()->startOfMonth();$to=$request->date('date_to')?->endOfDay() ?? now()->endOfDay();$open=(clone $opps)->whereNotIn('stage',['won','lost']);$won=(clone $opps)->where('stage','won');$due=$this->scope(CrmActivity::query(),$request)->where('status','open')->whereBetween('due_at',[$from,$to]);return response()->json(['data'=>['filters'=>['from'=>$from->toDateString(),'to'=>$to->toDateString()],'kpis'=>['active_customers'=>(clone $customers)->where('status','active')->count(),'new_leads'=>(clone $leads)->whereBetween('created_at',[$from,$to])->count(),'open_opportunities'=>$open->count(),'pipeline_value'=>$open->sum('estimated_value'),'followups_due'=>$due->count(),'won_opportunities'=>$won->whereBetween('updated_at',[$from,$to])->count(),'lead_conversion_rate'=>$leads->count()?round(((clone $leads)->where('state','converted')->count()/$leads->count())*100,2):null],'stages'=>(clone $opps)->select('stage',DB::raw('count(*) as count'),DB::raw('sum(estimated_value) as total'))->groupBy('stage')->get()]]);
    }
}
