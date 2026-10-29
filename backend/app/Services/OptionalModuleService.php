<?php
namespace App\Services; use App\Models\TenantModule; use App\Support\TenantContext;
final class OptionalModuleService { public function require(string $module):void { $tenant=app(TenantContext::class)->id(); abort_unless(TenantModule::where('tenant_id',$tenant)->where('module_key',$module)->where('enabled',true)->exists(),404,'This optional module is not enabled for the tenant.'); } public function set(string $tenant,string $module,bool $enabled):TenantModule{return TenantModule::updateOrCreate(['tenant_id'=>$tenant,'module_key'=>$module],['enabled'=>$enabled]);} }
