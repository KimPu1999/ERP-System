<?php
namespace App\Http\Controllers\Api\V1;
use App\Models\TenantModule; use App\Services\{AuthorizationService,OptionalModuleService}; use App\Support\TenantContext; use Illuminate\Http\Request;
final class ModuleController { public function index(){return response()->json(['data'=>TenantModule::where('tenant_id',app(TenantContext::class)->id())->orderBy('module_key')->get()]);} public function update(Request $r,OptionalModuleService $modules,AuthorizationService $auth){$t=app(TenantContext::class)->id();abort_unless($auth->allows($r->user(),$t,'organization.manage'),403);$d=$r->validate(['module_key'=>'required|in:pos,projects,fixed_assets','enabled'=>'required|boolean']);return response()->json(['data'=>$modules->set($t,$d['module_key'],$d['enabled'])]);} }
