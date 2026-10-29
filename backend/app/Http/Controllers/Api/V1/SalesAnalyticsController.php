<?php
namespace App\Http\Controllers\Api\V1;
use App\Models\{Customer,SalesInvoice}; use App\Support\TenantContext; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
final class SalesAnalyticsController { public function customerBalances(Request $r){$t=app(TenantContext::class)->id();$rows=Customer::where('tenant_id',$t)->select('customers.id','customers.name')->selectSub(SalesInvoice::whereColumn('customer_id','customers.id')->where('tenant_id',$t)->selectRaw('coalesce(sum(total-paid_total),0)'),'balance')->orderBy('name')->paginate(min($r->integer('per_page',25),100));return response()->json(['data'=>$rows]);} }
