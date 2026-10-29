<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class SalesNumberService { public function next(string $tenantId,string $type):string { $prefix=['quotation'=>'QUO','order'=>'SO','delivery'=>'DO','invoice'=>'INV','return'=>'RET','credit'=>'CN'][$type]??Str::upper($type); do{$number=$prefix.'-'.now()->format('Ym').'-'.Str::upper(Str::random(6));$exists=match($type){'quotation'=>DB::table('sales_quotations'),'order'=>DB::table('sales_orders'),'delivery'=>DB::table('delivery_orders'),'invoice'=>DB::table('sales_invoices'),'return'=>DB::table('sales_returns'),'credit'=>DB::table('credit_notes'),default=>null};}while($exists?->where('tenant_id',$tenantId)->where('number',$number)->exists()); return $number; } }
