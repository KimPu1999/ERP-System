<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\{CompanyMembership, GoodsReceipt, PurchaseOrder, PurchaseReturn};
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request};

final class PurchasingAnalyticsController
{
    public function performance(Request $request, AuthorizationService $auth): JsonResponse
    {
        $tenant=(string)app(TenantContext::class)->id(); $company=$request->string('company_id')->toString()?:null; if($company)abort_unless($auth->allows($request->user(),$tenant,'procurement.manage',$company),403); $ids=$company?[$company]:CompanyMembership::where('tenant_id',$tenant)->where('user_id',$request->user()->id)->where('status','active')->pluck('company_id')->all();
        $orders=PurchaseOrder::where('tenant_id',$tenant)->whereIn('company_id',$ids)->with('supplier')->when($request->filled('date_from'),fn($q)=>$q->whereDate('order_date','>=',$request->date('date_from')))->when($request->filled('date_to'),fn($q)=>$q->whereDate('order_date','<=',$request->date('date_to')))->get(); $returns=PurchaseReturn::where('tenant_id',$tenant)->whereIn('company_id',$ids)->with('purchaseOrder')->get(); $receipts=GoodsReceipt::where('tenant_id',$tenant)->whereIn('company_id',$ids)->where('state','posted')->with('items')->get();
        $rows=$orders->groupBy('supplier_id')->map(function($supplierOrders,$supplierId)use($returns,$receipts){$supplier=$supplierOrders->first()->supplier; $poIds=$supplierOrders->pluck('id'); $supplierReturns=$returns->whereIn('purchase_order_id',$poIds); $lines=$supplierOrders->flatMap->items; $received=$lines->sum(fn($i)=>(float)$i->received_quantity); $ordered=$lines->sum(fn($i)=>(float)$i->ordered_quantity); $accepted=$receipts->whereIn('purchase_order_id',$poIds)->flatMap->items->sum(fn($i)=>(float)$i->accepted_quantity); return ['supplier_id'=>$supplierId,'supplier'=>$supplier?->name,'purchase_order_count'=>$supplierOrders->count(),'purchase_value'=>(string)$supplierOrders->sum(fn($o)=>(float)($o->grand_total??0)),'on_time_delivery_available'=>false,'on_time_delivery'=>null,'fill_rate'=>$ordered>0?round(($received/$ordered)*100,2):null,'quality_acceptance_rate'=>$received>0?round(($accepted/$received)*100,2):null,'return_rate'=>$received>0?round($supplierReturns->flatMap->items->sum(fn($i)=>(float)$i->quantity)/$received*100,2):null,'overall_score_available'=>false,'overall_score'=>null,'score_note'=>'Not enough configured delivery samples for a reliable score.'];})->values();
        return response()->json(['data'=>$rows,'policy'=>['delivery_metric'=>'Unavailable until expected and actual delivery dates are complete','score'=>'No weighted score is produced without configured metric weights and minimum sample size']]);
    }
    public function analytics(Request $request, AuthorizationService $auth): JsonResponse
    {
        $tenant=(string)app(TenantContext::class)->id(); $company=$request->string('company_id')->toString()?:null; if($company)abort_unless($auth->allows($request->user(),$tenant,'procurement.manage',$company),403); $ids=$company?[$company]:CompanyMembership::where('tenant_id',$tenant)->where('user_id',$request->user()->id)->where('status','active')->pluck('company_id')->all(); $orders=PurchaseOrder::where('tenant_id',$tenant)->whereIn('company_id',$ids)->when($request->filled('date_from'),fn($q)=>$q->whereDate('order_date','>=',$request->date('date_from')))->when($request->filled('date_to'),fn($q)=>$q->whereDate('order_date','<=',$request->date('date_to')))->get(); $returns=PurchaseReturn::where('tenant_id',$tenant)->whereIn('company_id',$ids)->get(); return response()->json(['data'=>['currency_policy'=>'Values are grouped by currency and are not summed across currencies.','purchase_value_by_currency'=>$orders->groupBy(fn($o)=>$o->currency_id??'unassigned')->map(fn($set)=>(string)$set->sum(fn($o)=>(float)($o->grand_total??0))),'orders_by_status'=>$orders->groupBy('state')->map->count(),'returns_by_reason'=>$returns->groupBy('reason')->map->count(),'monthly_purchase_value'=>$orders->groupBy(fn($o)=>optional($o->order_date)->format('Y-m'))->map(fn($set)=>(string)$set->sum(fn($o)=>(float)($o->grand_total??0)))]]);
    }
}
