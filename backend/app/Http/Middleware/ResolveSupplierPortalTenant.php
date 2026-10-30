<?php
namespace App\Http\Middleware;
use App\Models\{SupplierPortalInvitation, SupplierPortalMembership};
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
final class ResolveSupplierPortalTenant {
    public function handle(Request $request, Closure $next): Response {
        $user=$request->user();abort_unless($user,401);
        if(DB::getDriverName()==='pgsql'){DB::statement("SELECT set_config('app.portal_user_id', ?, false)",[(string)$user->id]);}
        $token=(string)$request->input('token','');if($token&&DB::getDriverName()==='pgsql')DB::statement("SELECT set_config('app.portal_invitation_hash', ?, false)",[hash('sha256',$token)]);
        $context=app(TenantContext::class);
        try {
            $membership=SupplierPortalMembership::where('user_id',$user->id)->where('status','active')->first();
            $tenant=$membership?->tenant_id;
            if(!$tenant&&$token)$tenant=SupplierPortalInvitation::where('token_hash',hash('sha256',$token))->where('status','pending')->value('tenant_id');
            abort_unless($tenant,403,'Supplier portal access denied.');
            $context->set((string)$tenant);
            return $next($request);
        } finally {
            $context->clear();
            if(DB::getDriverName()==='pgsql'){DB::statement("SELECT set_config('app.portal_user_id', '', false)");DB::statement("SELECT set_config('app.portal_invitation_hash', '', false)");}
        }
    }
}
