<?php
namespace App\Http\Controllers\Api\V1;

use App\Jobs\GenerateReportExport;
use App\Jobs\DeliverSupplierNotification;
use App\Models\{AutomationRule,AutomationRun,Branch,Company,CompanyMembership,ErpNotification,PlatformAuditLog,PurchasingNotificationPreference,ReportExport,Supplier,SupplierAccessReview,SupplierAutomationRuleVersion,SupplierBill,SupplierComplianceReview,SupplierContract,SupplierDocument,SupplierDocumentRequirement,SupplierOnboardingApplication,SupplierPurchasingHold,SupplierReportSchedule,SupplierRiskAssessment,SupplierSavedReportView,SupplierNotificationDelivery,User};
use App\Services\{AuditLogger,AuthorizationService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Support\Facades\{Cache,DB,Schema};
use Illuminate\Validation\ValidationException;

final class SupplierIntelligenceController
{
    private const REPORTS=['supplier_directory'];
    private const TRIGGERS=AutomationRule::SUPPLIER_ALLOWLIST;
    private function tenant(): string { return (string) app(TenantContext::class)->id(); }
    private function scope(Request $r, AuthorizationService $auth, ?string $company=null): array { $t=$this->tenant(); if($company){abort_unless($auth->allows($r->user(),$t,'procurement.manage',$company),403); return [$t,[$company]];} $ids=$auth->accessibleCompanyIds($r->user(),$t,'procurement.manage'); abort_unless($ids===null||$ids!==[],403); return [$t,$ids]; }
    private function companyId(Request $r): ?string { return $r->string('company_id')->toString() ?: null; }
    public function analytics(Request $r, AuthorizationService $auth): JsonResponse
    {
        [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); $supplier=Supplier::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids));
        $docs=SupplierDocument::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids)); $risk=SupplierRiskAssessment::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids));
        $po=DB::table('purchase_orders')->where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids)); $bills=SupplierBill::where('tenant_id',$t)->whereIn('state',['posted','approved'])->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids));
        $status=(clone $supplier)->select('status',DB::raw('count(*) as count'))->groupBy('status')->pluck('count','status'); $category=(clone $supplier)->select('category',DB::raw('count(*) as count'))->groupBy('category')->pluck('count','category');
        $from=$r->date('date_from')?->startOfDay()??now()->subMonths(11)->startOfMonth(); $to=$r->date('date_to')?->endOfDay()??now()->endOfDay();
        $spend=(clone $bills)->whereBetween('accounting_date',[$from,$to])->select('currency_code',DB::raw('sum(coalesce(grand_total,total,0)) as amount'))->groupBy('currency_code')->get()->map(fn($x)=>['currency'=>$x->currency_code,'amount'=>(string)$x->amount])->values();
        $trend=(clone $bills)->whereBetween('accounting_date',[$from,$to])->selectRaw("to_char(accounting_date,'YYYY-MM') as period, currency_code, sum(coalesce(grand_total,total,0)) as amount")->groupBy('period','currency_code')->orderBy('period')->get()->map(fn($x)=>['period'=>$x->period,'currency'=>$x->currency_code,'amount'=>(string)$x->amount])->values();
        return response()->json(['data'=>['filters'=>['company_id'=>$this->companyId($r),'from'=>$from->toDateString(),'to'=>$to->toDateString(),'currency_policy'=>'Amounts remain separated by currency; no implicit conversion.'],'kpis'=>['total_suppliers'=>(clone $supplier)->count(),'active_suppliers'=>(clone $supplier)->where('status','active')->count(),'pending_approvals'=>SupplierOnboardingApplication::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids))->whereIn('approval_status',['pending','pending_review'])->count(),'expiring_documents'=>(clone $docs)->whereNotNull('expiry_date')->whereBetween('expiry_date',[now()->toDateString(),now()->addDays(90)->toDateString()])->count(),'high_risk_suppliers'=>(clone $risk)->where('status','approved')->whereIn('final_level',['high','critical'])->distinct('supplier_id')->count('supplier_id'),'automation_failures'=>AutomationRun::where('tenant_id',$t)->where('state','failed')->where('updated_at','>=',now()->subDays(30))->count(),'supplier_spend'=>$spend],'charts'=>['status_distribution'=>$status,'category_distribution'=>$category,'spend_trend'=>$trend,'contract_expiry'=>SupplierContract::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids))->whereBetween('ends_at',[now()->toDateString(),now()->addMonths(12)->toDateString()])->selectRaw("to_char(ends_at,'YYYY-MM') as period,count(*) as count")->groupBy('period')->orderBy('period')->get(),'automation_execution_trend'=>AutomationRun::where('tenant_id',$t)->where('created_at','>=',now()->subMonths(6))->selectRaw("to_char(created_at,'YYYY-MM') as period,state,count(*) as count")->groupBy('period','state')->orderBy('period')->get()],'definitions'=>['supplier_spend'=>'Posted/approved supplier bills by their recorded currency and accounting date.','pending_approvals'=>'Supplier onboarding applications with pending review state.','expiring_documents'=>'Supplier documents expiring within 90 days from server date.']]]);
    }
    public function reports(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); return response()->json(['data'=>collect(self::REPORTS)->map(fn($key)=>['key'=>$key,'name'=>str_replace('_',' ',ucwords($key)),'formats'=>$key==='supplier_directory'?['csv']:[],'available'=>$key==='supplier_directory'])->values(),'scope'=>['company_ids'=>$ids]]); }
    public function exports(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); $d=$r->validate(['report_key'=>'required|in:supplier_directory','format'=>'required|in:csv','filters'=>'nullable|array']); abort_unless($auth->allows($r->user(),$t,'reports.export',$this->companyId($r)),403); $filters=$d['filters']??[]; $filters['company_id']=$this->companyId($r); $export=ReportExport::create(['tenant_id'=>$t,'requested_by'=>$r->user()->id,'report_key'=>$d['report_key'],'format'=>'csv','filters'=>$filters,'state'=>'queued']); GenerateReportExport::dispatch($t,$export->id); return response()->json(['data'=>$export],202); }
    public function savedViews(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); return response()->json(['data'=>SupplierSavedReportView::where('tenant_id',$t)->where('user_id',$r->user()->id)->when(is_array($ids),fn($q)=>$q->where(function($x)use($ids){$x->whereNull('company_id')->orWhereIn('company_id',$ids);}))->latest()->paginate(25)]); }
    public function saveView(Request $r, AuthorizationService $auth, AuditLogger $audit): JsonResponse { [$t]= $this->scope($r,$auth,$this->companyId($r)); $d=$r->validate(['company_id'=>'nullable|uuid','name'=>'required|string|max:150','report_key'=>'required|in:'.implode(',',self::REPORTS),'filters'=>'nullable|array','is_default'=>'sometimes|boolean','is_shared'=>'sometimes|boolean']); $v=SupplierSavedReportView::create([...$d,'tenant_id'=>$t,'user_id'=>$r->user()->id]); $audit->record($r,'supplier_report_view.created',$t,$v); return response()->json(['data'=>$v],201); }
    public function schedules(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); return response()->json(['data'=>SupplierReportSchedule::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids))->latest()->paginate(25)]); }
    public function saveSchedule(Request $r, AuthorizationService $auth, AuditLogger $audit): JsonResponse { [$t]= $this->scope($r,$auth,$this->companyId($r)); abort_unless($auth->allows($r->user(),$t,'reports.schedule',$this->companyId($r)),403); $d=$r->validate(['company_id'=>'required|uuid','name'=>'required|string|max:150','report_key'=>'required|in:'.implode(',',self::REPORTS),'filters'=>'nullable|array','frequency'=>'required|in:daily,weekly,monthly,quarterly','timezone'=>'required|string|max:80','format'=>'required|in:csv','recipients'=>'required|array|min:1','recipients.*'=>'email']); $s=SupplierReportSchedule::create([...$d,'tenant_id'=>$t,'enabled'=>false,'created_by'=>$r->user()->id]); $audit->record($r,'supplier_report_schedule.created',$t,$s); return response()->json(['data'=>$s],201); }
    public function automationTypes(Request $r, AuthorizationService $auth): JsonResponse { $this->manageScope($r,$auth); return response()->json(['data'=>collect(self::TRIGGERS)->map(fn($type)=>['key'=>$type,'name'=>str_replace('_',' ',ucwords($type)),'configuration_keys'=>$this->configurationKeys($type)])->values()]); }
    public function automation(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->automationScope($r,$auth,$this->companyId($r)); return response()->json(['data'=>AutomationRule::where('tenant_id',$t)->whereIn('event_key',self::TRIGGERS)->when(is_array($ids),fn($q)=>$q->where(function($x)use($ids){$x->whereNull('company_id')->orWhereIn('company_id',$ids);}))->latest()->paginate(min(max($r->integer('per_page',25),10),100))]); }
    public function saveAutomation(Request $r, AuthorizationService $auth, AuditLogger $audit): JsonResponse { $t=$this->tenant(); $this->automationManageScope($r,$auth,$r->input('company_id')); $d=$this->validatedAutomation($r,$t,$auth); $this->rejectDuplicate($t,$d); $rule=DB::transaction(function()use($d,$t,$r){$rule=AutomationRule::create([...$d,'actions'=>[],'tenant_id'=>$t,'is_active'=>false,'created_by'=>$r->user()->id]); SupplierAutomationRuleVersion::create(['tenant_id'=>$t,'rule_id'=>$rule->id,'version'=>1,'definition'=>$d,'created_by'=>$r->user()->id]); return $rule;}); $audit->record($r,'supplier_automation_rule.created',$t,$rule); return response()->json(['data'=>$rule],201); }
    public function updateAutomation(Request $r, AutomationRule $rule, AuthorizationService $auth, AuditLogger $audit): JsonResponse { abort_unless($rule->tenant_id===$this->tenant() && in_array($rule->event_key,self::TRIGGERS,true),404); abort_unless($auth->allows($r->user(),$this->tenant(),'automation.manage',$rule->company_id),403); $d=$this->validatedAutomation($r,$this->tenant(),$auth); $this->rejectDuplicate($this->tenant(),$d,$rule->id); DB::transaction(function()use($d,$rule,$r){$rule->update($d); SupplierAutomationRuleVersion::create(['tenant_id'=>$rule->tenant_id,'rule_id'=>$rule->id,'version'=>((int)SupplierAutomationRuleVersion::where('rule_id',$rule->id)->max('version'))+1,'definition'=>$d,'created_by'=>$r->user()->id]);}); $audit->record($r,'supplier_automation_rule.changed',$this->tenant(),$rule); return response()->json(['data'=>$rule->fresh()]); }
    public function toggleAutomation(Request $r, AutomationRule $rule, AuthorizationService $auth, AuditLogger $audit): JsonResponse { abort_unless($rule->tenant_id===$this->tenant() && in_array($rule->event_key,self::TRIGGERS,true),404); abort_unless($auth->allows($r->user(),$this->tenant(),'automation.manage',$rule->company_id),403); $enabled=$r->boolean('enabled'); $rule->update(['is_active'=>$enabled]); $audit->record($r,$enabled?'supplier_automation_rule.enabled':'supplier_automation_rule.paused',$this->tenant(),$rule); return response()->json(['data'=>$rule]); }
    public function automationRuns(Request $r, AutomationRule $rule, AuthorizationService $auth): JsonResponse { abort_unless($rule->tenant_id===$this->tenant(),404); abort_unless($auth->allows($r->user(),$this->tenant(),'automation.manage',$rule->company_id),403); return response()->json(['data'=>AutomationRun::where('tenant_id',$this->tenant())->where('rule_id',$rule->id)->latest()->paginate(min(max($r->integer('per_page',25),10),100))]); }
    public function monitoring(Request $r, AuthorizationService $auth): JsonResponse
    {
        [$t, $ids] = $this->automationScope($r, $auth, $this->companyId($r));
        $ruleIds = AutomationRule::where('tenant_id', $t)->whereIn('event_key', self::TRIGGERS)->when(is_array($ids), fn ($q) => $q->where(function ($x) use ($ids): void { $x->whereNull('company_id')->orWhereIn('company_id', $ids); }))->pluck('id');
        $base = $this->monitoringRunQuery($r, $t, $ids, $ruleIds);
        $total = (clone $base)->count();
        $successful = (clone $base)->where('state', 'completed')->count();
        $failed = (clone $base)->where('state', 'failed')->count();
        $runs = (clone $base)->with('rule')->orderBy($this->monitoringSort($r), $r->string('direction')->toString() === 'asc' ? 'asc' : 'desc')->paginate(min(max($r->integer('per_page', 25), 10), 100));
        return response()->json(['data' => [
            'kpis' => ['total_runs' => $total, 'successful_runs' => $successful, 'failed_runs' => $failed, 'pending_jobs' => (clone $base)->whereIn('state', ['queued', 'running'])->count(), 'success_rate' => ($successful + $failed) > 0 ? round(($successful / ($successful + $failed)) * 100, 2) : null, 'open_alerts' => $this->supplierAlertCount($t, $ids), 'failed_notification_deliveries' => SupplierNotificationDelivery::where('tenant_id', $t)->where('state', 'failed')->whereHas('notification', fn ($q) => $this->companyQuery($q, $ids))->count()],
            'runs' => $runs,
            'health' => $this->systemHealth($r->user()->is_platform_super_admin === true),
            'filters' => ['company_id' => $this->companyId($r), 'supplier_id' => $r->string('supplier_id')->toString() ?: null, 'automation_type' => $r->string('automation_type')->toString() ?: null],
        ]]);
    }

    public function monitoringRun(Request $r, AutomationRun $run, AuthorizationService $auth): JsonResponse
    {
        abort_unless($run->tenant_id === $this->tenant(), 404);
        abort_unless($auth->allows($r->user(), $this->tenant(), 'automation.manage', $run->company_id), 403);
        return response()->json(['data' => $run->load(['rule', 'notifications.deliveries'])]);
    }

    public function monitoringDeliveries(Request $r, AuthorizationService $auth): JsonResponse
    {
        [$t, $ids] = $this->automationScope($r, $auth, $this->companyId($r));
        $q = SupplierNotificationDelivery::where('tenant_id', $t)->with(['notification', 'notification.user'])->whereHas('notification', function ($x) use ($ids, $r): void {
            $this->companyQuery($x, $ids);
            $x->when($r->filled('supplier_id'), fn ($n) => $n->where('supplier_id', $r->string('supplier_id')))->when($r->filled('automation_type'), fn ($n) => $n->where('type', $r->string('automation_type')));
        })->when($r->filled('state'), fn ($x) => $x->where('state', $r->string('state')))->when($r->filled('date_from'), fn ($x) => $x->whereDate('created_at', '>=', $r->date('date_from')))->when($r->filled('date_to'), fn ($x) => $x->whereDate('created_at', '<=', $r->date('date_to')))->latest();
        return response()->json(['data' => $q->paginate(min(max($r->integer('per_page', 25), 10), 100))->through(fn (SupplierNotificationDelivery $delivery) => $this->deliveryPayload($delivery))]);
    }

    public function monitoringAlerts(Request $r, AuthorizationService $auth): JsonResponse
    {
        [$t, $ids] = $this->automationScope($r, $auth, $this->companyId($r));
        return response()->json(['data' => $this->supplierAlerts($t, $ids, $r->integer('limit', 100))]);
    }

    public function monitoringFailedJobs(Request $r): JsonResponse
    {
        abort_unless($r->user()->is_platform_super_admin === true, 403);
        if (! Schema::hasTable('failed_jobs')) return response()->json(['data' => [], 'available' => false, 'message' => 'Failed job storage is not available.']);
        $jobs = DB::table('failed_jobs')->latest('failed_at')->limit(min(max($r->integer('limit', 100), 1), 100))->get(['uuid', 'connection', 'queue', 'failed_at', 'exception']);
        return response()->json(['data' => $jobs->map(fn ($job) => ['id' => $job->uuid, 'queue' => $job->queue, 'connection' => $job->connection, 'failed_at' => $job->failed_at, 'failure_classification' => $this->failedJobClassification($job->exception), 'message' => $this->sanitizedFailureMessage($job->exception), 'retry_eligible' => false])->values(), 'available' => true]);
    }

    public function retryMonitoringDelivery(Request $r, SupplierNotificationDelivery $delivery, AuthorizationService $auth, AuditLogger $audit): JsonResponse
    {
        $notification = $delivery->notification()->firstOrFail();
        abort_unless($delivery->tenant_id === $this->tenant(), 404);
        abort_unless($auth->allows($r->user(), $this->tenant(), 'automation.manage', $notification->company_id), 403);
        if ($delivery->state !== 'failed') throw ValidationException::withMessages(['delivery' => ['Only failed notification deliveries may be retried.']]);
        if ((int) $delivery->attempts >= 3) throw ValidationException::withMessages(['delivery' => ['The delivery retry limit has been reached.']]);
        $rule = $notification->rule_id ? AutomationRule::where('tenant_id', $this->tenant())->find($notification->rule_id) : null;
        abort_unless($rule && $rule->is_active && in_array($rule->event_key, self::TRIGGERS, true), 422, 'The automation rule is no longer eligible for retry.');
        DB::transaction(function () use ($delivery, $notification): void {
            $locked = SupplierNotificationDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'failed') throw ValidationException::withMessages(['delivery' => ['This delivery is already queued or completed.']]);
            $locked->update(['state' => 'queued', 'queued_at' => now(), 'last_error' => null]);
            $notification->update(['delivery_state' => 'pending', 'last_error' => null]);
        });
        $audit->record($r, 'supplier_notification_delivery.retry_requested', $this->tenant(), $delivery, ['notification_id' => $notification->id, 'channel' => $delivery->channel]);
        DeliverSupplierNotification::dispatch($this->tenant(), $notification->id, $delivery->channel);
        return response()->json(['data' => $delivery->fresh(), 'status' => 'queued'], 202);
    }
    public function notifications(Request $r): JsonResponse { $q=ErpNotification::where('tenant_id',$this->tenant())->where('user_id',$r->user()->id)->where('channel','in_app')->when($r->boolean('unread'),fn($x)=>$x->whereNull('read_at'))->when($r->boolean('read'),fn($x)=>$x->whereNotNull('read_at'))->when($r->filled('type'),fn($x)=>$x->where('type',$r->string('type')))->when($r->filled('priority'),fn($x)=>$x->where('priority',$r->string('priority')))->when($r->filled('supplier_id'),fn($x)=>$x->where('supplier_id',$r->string('supplier_id')))->when($r->filled('search'),fn($x)=>$x->where(fn($s)=>$s->where('title','ilike','%'.$r->string('search').'%')->orWhere('body','ilike','%'.$r->string('search').'%')))->when($r->filled('date_from'),fn($x)=>$x->whereDate('created_at','>=',$r->date('date_from')))->when($r->filled('date_to'),fn($x)=>$x->whereDate('created_at','<=',$r->date('date_to')))->latest(); return response()->json(['data'=>$q->paginate(min(max($r->integer('per_page',25),10),100)),'unread_count'=>ErpNotification::where('tenant_id',$this->tenant())->where('user_id',$r->user()->id)->where('channel','in_app')->whereNull('read_at')->count()]); }
    public function notification(Request $r, ErpNotification $notification): JsonResponse { abort_unless($notification->tenant_id===$this->tenant() && $notification->user_id===$r->user()->id && $notification->channel==='in_app',404); return response()->json(['data'=>$notification->load('deliveries')]); }
    public function unreadNotificationCount(Request $r): JsonResponse { return response()->json(['data'=>['count'=>ErpNotification::where('tenant_id',$this->tenant())->where('user_id',$r->user()->id)->where('channel','in_app')->whereNull('read_at')->count()]]); }
    public function readNotification(Request $r, ErpNotification $notification): JsonResponse { abort_unless($notification->tenant_id===$this->tenant() && $notification->user_id===$r->user()->id && $notification->channel==='in_app',404); $notification->update(['read_at'=>now()]); return response()->json(['data'=>$notification->fresh()]); }
    public function readAllNotifications(Request $r): JsonResponse { ErpNotification::where('tenant_id',$this->tenant())->where('user_id',$r->user()->id)->where('channel','in_app')->whereNull('read_at')->update(['read_at'=>now()]); return response()->json(['data'=>['count'=>0]]); }
    public function notificationPreferences(Request $r, AuthorizationService $auth): JsonResponse { $tenant=$this->tenant(); $company=$r->string('company_id')->toString()?:null; if($company)abort_unless($auth->allows($r->user(),$tenant,'procurement.manage',$company) || CompanyMembership::where('tenant_id',$tenant)->where('company_id',$company)->where('user_id',$r->user()->id)->where('status','active')->exists(),403); return response()->json(['data'=>PurchasingNotificationPreference::where('tenant_id',$tenant)->where('user_id',$r->user()->id)->when($company,fn($q)=>$q->where('company_id',$company))->latest()->get()]); }
    public function saveNotificationPreference(Request $r, AuthorizationService $auth, AuditLogger $audit): JsonResponse { $tenant=$this->tenant(); $d=$r->validate(['company_id'=>'nullable|uuid','event_type'=>'required|string|in:'.implode(',', [...self::TRIGGERS,'automation_execution_failure']),'channel'=>'required|in:in_app,email','frequency'=>'required|in:immediate,daily,digest','is_enabled'=>'required|boolean','reminder_interval_minutes'=>'nullable|integer|min:1|max:525600']); $company=$d['company_id']??null; if($company)abort_unless($auth->allows($r->user(),$tenant,'procurement.manage',$company) || CompanyMembership::where('tenant_id',$tenant)->where('company_id',$company)->where('user_id',$r->user()->id)->where('status','active')->exists(),403); $pref=PurchasingNotificationPreference::updateOrCreate(['tenant_id'=>$tenant,'company_id'=>$company,'user_id'=>$r->user()->id,'event_type'=>$d['event_type'],'channel'=>$d['channel']],$d); $audit->record($r,'supplier.notifications.preference.changed',$tenant,$pref,['company_id'=>$company]); return response()->json(['data'=>$pref]); }
    public function notificationDeliveries(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->automationScope($r,$auth,$this->companyId($r)); abort_unless($auth->allows($r->user(),$t,'automation.manage',$this->companyId($r)),403); $q=SupplierNotificationDelivery::where('tenant_id',$t)->whereHas('notification',fn($x)=>$x->whereIn('channel',['in_app','email'])->when(is_array($ids),fn($n)=>$n->whereIn('company_id',$ids)))->when($r->filled('state'),fn($x)=>$x->where('state',$r->string('state')))->latest(); return response()->json(['data'=>$q->paginate(min(max($r->integer('per_page',25),10),100))]); }

    private function monitoringRunQuery(Request $r, string $tenant, ?array $companyIds, $ruleIds)
    {
        return AutomationRun::where('tenant_id', $tenant)->whereIn('rule_id', $ruleIds)
            ->when(is_array($companyIds), fn ($q) => $q->where(function ($x) use ($companyIds): void { $x->whereNull('company_id')->orWhereIn('company_id', $companyIds); }))
            ->when($r->filled('supplier_id'), fn ($q) => $q->whereHas('notifications', fn ($n) => $n->where('supplier_id', $r->string('supplier_id'))))
            ->when($r->filled('automation_type'), fn ($q) => $q->where('automation_type', $r->string('automation_type')))
            ->when($r->filled('status'), fn ($q) => $q->where('state', $r->string('status')))
            ->when($r->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('id', $r->string('search'))->orWhere('idempotency_key', 'ilike', '%'.$r->string('search').'%')->orWhere('correlation_id', 'ilike', '%'.$r->string('search').'%')))
            ->when($r->filled('date_from'), fn ($q) => $q->whereDate('started_at', '>=', $r->date('date_from')))
            ->when($r->filled('date_to'), fn ($q) => $q->whereDate('started_at', '<=', $r->date('date_to')));
    }

    private function monitoringSort(Request $r): string
    {
        return in_array($r->string('sort')->toString(), ['started_at', 'completed_at', 'created_at', 'state'], true) ? $r->string('sort')->toString() : 'started_at';
    }

    private function companyQuery($query, ?array $companyIds): void
    {
        if (is_array($companyIds)) $query->whereIn('company_id', $companyIds);
    }

    private function deliveryPayload(SupplierNotificationDelivery $delivery): array
    {
        $notification = $delivery->notification;
        $status = match ($delivery->state) {
            'queued' => 'queued',
            'attempted', 'attempting' => 'attempted',
            'accepted_by_mailer', 'accepted_by_configured_mailer' => 'accepted',
            'succeeded' => 'confirmed',
            default => 'failed',
        };
        return ['id' => $delivery->id, 'notification_id' => $delivery->notification_id, 'automation_run_id' => $notification?->automation_run_id, 'rule_id' => $notification?->rule_id, 'automation_type' => $notification?->type, 'supplier_id' => $notification?->supplier_id, 'company_id' => $notification?->company_id, 'recipient' => $delivery->channel === 'email' ? $notification?->user?->email : $notification?->user?->name, 'channel' => $delivery->channel, 'state' => $delivery->state, 'status' => $status, 'attempts' => $delivery->attempts, 'queued_at' => $delivery->queued_at, 'attempted_at' => $delivery->attempted_at, 'accepted_at' => $delivery->accepted_at, 'confirmed_at' => $delivery->confirmed_at, 'succeeded_at' => $delivery->succeeded_at, 'last_error' => $delivery->last_error];
    }

    private function supplierAlerts(string $tenant, ?array $companyIds, int $limit): array
    {
        $limit = min(max($limit, 1), 100);
        $alerts = collect();
        SupplierPurchasingHold::where('tenant_id', $tenant)->where('status', 'active')->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->with('supplier')->latest()->limit($limit)->get()->each(fn ($row) => $alerts->push(['id' => $row->id, 'type' => 'purchasing_hold', 'severity' => 'high', 'status' => 'open', 'supplier_id' => $row->supplier_id, 'supplier_name' => $row->supplier?->name, 'company_id' => $row->company_id, 'related_record_id' => $row->id, 'created_at' => $row->created_at]));
        SupplierRiskAssessment::where('tenant_id', $tenant)->where('status', 'approved')->whereIn('final_level', ['high', 'critical'])->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->with('supplier')->latest()->limit($limit)->get()->each(fn ($row) => $alerts->push(['id' => $row->id, 'type' => 'supplier_risk', 'severity' => $row->final_level, 'status' => 'open', 'supplier_id' => $row->supplier_id, 'supplier_name' => $row->supplier?->name, 'company_id' => $row->company_id, 'related_record_id' => $row->id, 'created_at' => $row->created_at]));
        SupplierBill::where('tenant_id', $tenant)->whereIn('state', ['posted', 'approved'])->whereDate('due_date', '<', now()->toDateString())->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->with('supplier')->latest('due_date')->limit($limit)->get()->each(fn ($row) => $alerts->push(['id' => $row->id, 'type' => 'overdue_supplier_bill', 'severity' => 'high', 'status' => 'open', 'supplier_id' => $row->supplier_id, 'supplier_name' => $row->supplier?->name, 'company_id' => $row->company_id, 'related_record_id' => $row->id, 'created_at' => $row->created_at]));
        ErpNotification::where('tenant_id', $tenant)->whereNull('read_at')->whereNotNull('supplier_id')->whereIn('type', self::TRIGGERS)->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->with('supplier')->latest()->limit($limit)->get()->each(fn ($row) => $alerts->push(['id' => $row->id, 'type' => $row->type, 'severity' => $row->priority ?: 'normal', 'status' => 'open', 'supplier_id' => $row->supplier_id, 'supplier_name' => $row->supplier?->name, 'company_id' => $row->company_id, 'related_record_id' => $row->automation_run_id, 'created_at' => $row->created_at]));
        return $alerts->sortByDesc('created_at')->take($limit)->values()->all();
    }

    private function supplierAlertCount(string $tenant, ?array $companyIds): int
    {
        return SupplierPurchasingHold::where('tenant_id', $tenant)->where('status', 'active')->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->count()
            + SupplierRiskAssessment::where('tenant_id', $tenant)->where('status', 'approved')->whereIn('final_level', ['high', 'critical'])->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->count()
            + SupplierBill::where('tenant_id', $tenant)->whereIn('state', ['posted', 'approved'])->whereDate('due_date', '<', now()->toDateString())->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->count()
            + ErpNotification::where('tenant_id', $tenant)->whereNull('read_at')->whereNotNull('supplier_id')->whereIn('type', self::TRIGGERS)->when(is_array($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))->count();
    }

    private function systemHealth(bool $platformOperator): array
    {
        $databaseQueue = config('queue.default') === 'database';
        $canObserveGlobalQueue = $platformOperator && $databaseQueue && Schema::hasTable('jobs');
        $canObserveFailedJobs = $platformOperator && Schema::hasTable('failed_jobs');
        return ['queue' => ['driver' => config('queue.default'), 'backlog' => $canObserveGlobalQueue ? DB::table('jobs')->count() : null, 'worker_activity' => null, 'status' => $canObserveGlobalQueue ? 'observable_backlog_only' : 'unknown'], 'failed_jobs' => ['available' => $canObserveFailedJobs, 'count' => $canObserveFailedJobs ? DB::table('failed_jobs')->count() : null], 'scheduler' => ['last_run_at' => Cache::get('erp:purchasing-automation:last_run'), 'status' => Cache::has('erp:purchasing-automation:last_run') ? 'observed' : 'unknown']];
    }

    private function failedJobClassification(string $exception): string
    {
        return str_contains($exception, 'QueryException') || str_contains($exception, 'SQLSTATE') ? 'database' : (str_contains($exception, 'AuthorizationException') ? 'authorization' : 'execution');
    }

    private function sanitizedFailureMessage(string $exception): string
    {
        $line = trim(strtok($exception, "\n") ?: 'Queue job failed.');
        return mb_substr(preg_replace('/(password|token|secret|authorization)[^,;\s]*/i', '$1=[redacted]', $line) ?: 'Queue job failed.', 0, 240);
    }

    private function manageScope(Request $r, AuthorizationService $auth, ?string $company=null): void { $tenant=$this->tenant(); abort_unless($auth->allows($r->user(),$tenant,'automation.manage',$company) || $auth->allows($r->user(),$tenant,'procurement.manage',$company),403); }
    private function automationManageScope(Request $r, AuthorizationService $auth, ?string $company=null): void { abort_unless($auth->allows($r->user(),$this->tenant(),'automation.manage',$company),403); }
    private function automationScope(Request $r, AuthorizationService $auth, ?string $company=null): array { $tenant=$this->tenant(); $this->manageScope($r,$auth,$company); if($company)return [$tenant,[$company]]; $ids=$auth->accessibleCompanyIds($r->user(),$tenant,'automation.manage'); if($ids===[])$ids=$auth->accessibleCompanyIds($r->user(),$tenant,'procurement.manage'); abort_unless($ids===null||$ids!==[],403); return [$tenant,$ids]; }
    private function validatedAutomation(Request $r, string $tenant, AuthorizationService $auth): array { $d=$r->validate(['company_id'=>'nullable|uuid','branch_id'=>'nullable|uuid','automation_type'=>'required|string','name'=>'required|string|max:150','configuration'=>'nullable|array','frequency'=>'nullable|in:once,daily,weekly,monthly','channels'=>'nullable|array','channels.*'=>'in:in_app,email','recipients'=>'nullable|array','recipients.*'=>'uuid']); $type=(string)$d['automation_type']; abort_unless(in_array($type,self::TRIGGERS,true),422); $config=$d['configuration']??[]; $allowed=$this->configurationKeys($type); $unknown=array_diff(array_keys($config),$allowed); if($unknown)throw ValidationException::withMessages(['configuration'=>['Unsupported configuration key: '.reset($unknown)]]); if(isset($config['supplier_id'])){abort_unless(is_string($config['supplier_id']) && Supplier::where('tenant_id',$tenant)->whereKey($config['supplier_id'])->when($d['company_id']??null,fn($q)=>$q->where(fn($x)=>$x->whereNull('company_id')->orWhere('company_id',$d['company_id'])))->exists(),422,'The selected supplier is not available.');} foreach(['days_before','days_overdue','days_open','window_days'] as $key)if(array_key_exists($key,$config)&&(!is_int($config[$key])||$config[$key]<0||$config[$key]>3650))throw ValidationException::withMessages(['configuration'=>[$key.' must be a whole number between 0 and 3650.']]); if(array_key_exists('threshold',$config)&&(!is_numeric($config['threshold'])||$config['threshold']<0||$config['threshold']>100))throw ValidationException::withMessages(['configuration'=>['threshold must be between 0 and 100.']]); $company=$d['company_id']??null; if($company){abort_unless($auth->allows($r->user(),$tenant,'automation.manage',$company),403); abort_unless(Company::where('tenant_id',$tenant)->whereKey($company)->where('is_active',true)->exists(),422,'The selected company is not available.');} if(!empty($d['branch_id']))abort_unless(Branch::where('tenant_id',$tenant)->whereKey($d['branch_id'])->exists() && Branch::whereKey($d['branch_id'])->where('company_id',$company)->exists(),422,'The selected branch is not available for this company.'); $recipients=array_values($d['recipients']??[]); if($recipients){$valid=User::whereIn('id',$recipients)->where('status','active')->whereHas('tenantMemberships',fn($q)=>$q->where('tenant_id',$tenant)->where('status','active'))->count(); abort_unless($valid===count($recipients),422,'All recipients must be active members of this tenant.');} $channels=array_values($d['channels']??[]); return ['company_id'=>$company,'branch_id'=>$d['branch_id']??null,'event_key'=>$type,'name'=>$d['name'],'conditions'=>$config,'frequency'=>$d['frequency']??null,'channels'=>$channels!==[]?$channels:['in_app'],'recipients'=>$recipients]; }
    private function configurationKeys(string $type): array { return match($type){ 'supplier_onboarding_reminder'=>['supplier_id','days_before'], 'supplier_missing_document_reminder'=>['supplier_id','days_before'], 'supplier_risk_alert'=>['supplier_id','threshold'], 'supplier_overdue_bill_reminder'=>['supplier_id','days_overdue'], 'supplier_purchase_approval_reminder'=>['days_overdue'], 'supplier_hold_review_alert'=>['supplier_id','days_open'], default=>[] }; }
    private function rejectDuplicate(string $tenant,array $data,?string $ignore=null): void { $exists=AutomationRule::where('tenant_id',$tenant)->where('event_key',$data['event_key'])->where('name',$data['name'])->when($data['company_id']??null,fn($q)=>$q->where('company_id',$data['company_id']),fn($q)=>$q->whereNull('company_id'))->when($ignore,fn($q)=>$q->where('id','<>',$ignore))->exists(); if($exists)throw ValidationException::withMessages(['name'=>['An automation rule with this name already exists in this company scope.']]); }
    public function portalActivity(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); return response()->json(['data'=>PlatformAuditLog::where('tenant_id',$t)->where('action','like','supplier_portal.%')->when(is_array($ids),fn($q)=>$q->whereIn('metadata->company_id',$ids))->latest()->paginate(30)]); }
    public function accessReviews(Request $r, AuthorizationService $auth): JsonResponse { [$t,$ids]=$this->scope($r,$auth,$this->companyId($r)); return response()->json(['data'=>SupplierAccessReview::where('tenant_id',$t)->when(is_array($ids),fn($q)=>$q->whereIn('company_id',$ids))->with(['supplier','membership'])->latest()->paginate(25)]); }
}
