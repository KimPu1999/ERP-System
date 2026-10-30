<?php
namespace App\Jobs;
use App\Jobs\Concerns\HasTenantContext;
use App\Models\{ReportExport,Supplier};
use App\Services\AccountingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

final class GenerateReportExport implements ShouldQueue
{
    use Queueable, HasTenantContext;
    public function __construct(string $tenantId, public readonly string $exportId) { $this->tenantId=$tenantId; }
    public function handle(AccountingService $accounting): void
    {
        $export=ReportExport::where('tenant_id',$this->tenantId)->findOrFail($this->exportId);
        try {
            $filters=$export->filters??[]; $headers=[]; $rows=[];
            if ($export->report_key==='trial_balance') { $headers=['Account','Type','Debit','Credit','Balance']; $rows=array_map(fn($r)=>[$r['account'],$r['account_type'],$r['debit'],$r['credit'],$r['balance']],$accounting->trialBalance($this->tenantId,$filters['company_id'],$filters['from'],$filters['to'])); }
            else { $headers=['Supplier Code','Supplier Name','Status','Category','Company','Created Date']; $rows=Supplier::where('tenant_id',$this->tenantId)->when($filters['company_id']??null,fn($q,$id)=>$q->where('company_id',$id))->with('company')->orderBy('name')->get()->map(fn($s)=>[$s->code,$s->name,$s->status,$s->category,$s->company?->name,$s->created_at?->toDateString()])->all(); }
            $quote=fn($v)=>'"'.str_replace('"','""',str_replace(["\r","\n"],' ',(string)$v)).'"'; $csv=implode(',',array_map($quote,$headers))."\n"; foreach($rows as $row)$csv.=implode(',',array_map($quote,$row))."\n";
            $path='private/reports/'.$export->id.'.csv'; Storage::disk('local')->put($path,$csv); $export->update(['state'=>'completed','private_path'=>$path,'completed_at'=>now()]);
        } catch (\Throwable $e) { $export->update(['state'=>'failed','error'=>$e->getMessage()]); throw $e; }
    }
}
