<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MfaService;
use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class MfaController
{
    public function enroll(Request $request, MfaService $mfa): JsonResponse { abort_if($request->user()->mfa_enabled_at, 422, 'MFA is already enabled.'); $request->session()->forget('mfa.pending_secret'); $secret = $mfa->generateSecret(); $request->session()->put('mfa.pending_secret', $mfa->encrypt($secret)); return response()->json(['secret' => $secret, 'otpauth_uri' => $mfa->provisioningUri($request->user(), $secret)]); }
    public function confirm(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['code' => ['required', 'digits:6']]); $encrypted = $request->session()->get('mfa.pending_secret'); $secret = $encrypted ? Crypt::decryptString($encrypted) : null; if (! $secret || ! $mfa->verify($secret, $data['code'])) { $events->record($request, 'auth.mfa_failed'); abort(422, 'Invalid MFA code.'); } $loginPending = (bool) $request->session()->pull('auth.login_pending', false); $newDevice = (bool) $request->session()->pull('auth.new_device', false); $mode = (string) $request->session()->pull('auth.mfa_mode', 'business'); $request->session()->forget(['mfa.pending_secret', 'security.step_up_until']); $codes = $mfa->recoveryCodes(); $request->user()->update(['mfa_secret_encrypted' => $mfa->encrypt($secret), 'mfa_enabled_at' => now(), 'mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes)]); DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete(); $events->record($request, 'auth.mfa_enabled'); if ($loginPending) { $request->user()->forceFill(['last_login_at' => now()])->saveQuietly(); $events->record($request, $mode === 'platform' ? 'auth.platform_login_succeeded' : 'auth.business_login_succeeded', $request->user()->id); if ($newDevice) $events->record($request, 'auth.new_device', $request->user()->id, null, ['device_hash' => hash('sha256', (string) $request->userAgent())]); } return response()->json(['recovery_codes' => $codes]); }
    public function verifyLogin(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = \App\Models\User::find($request->session()->get('auth.mfa_user_id'));
        $mode = (string) $request->session()->get('auth.mfa_mode', 'business');
        abort_unless($user, 401, 'MFA challenge expired.');
        abort_unless($user->status === 'active' && ! $user->suspended_at, 423, __('auth.suspended'));
        if ($mode === 'platform') abort_unless($user->is_platform_super_admin === true && $user->hasVerifiedEmail(), 403, 'Active verified platform administrator access required.');
        else { abort_if($user->is_platform_super_admin === true, 403, 'Use the platform administrator sign-in.'); abort_unless($user->tenantMemberships()->where('status', 'active')->exists() || $user->invitations()->where('status', 'pending')->whereNull('accepted_at')->where('expires_at', '>', now())->exists(), 403, 'An active business workspace membership is required.'); }

        $key = 'mfa-login:'.$user->id.'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 10), 429, 'Too many invalid MFA attempts. Please start sign-in again later.');
        $valid = $mfa->verify($mfa->decrypt($user) ?? '', $data['code']) || $mfa->consumeRecoveryCode($user, $data['code']);
        if (! $valid) { RateLimiter::hit($key, 600); $events->record($request, 'auth.mfa_failed', $user->id); abort(422, 'Invalid MFA code. Check your authenticator time and enter the current six-digit code.'); }
        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('auth.mfa_remember', false); $newDevice = (bool) $request->session()->pull('auth.new_device', false);
        $request->session()->forget(['auth.mfa_user_id', 'auth.mfa_mode', 'auth.login_pending']); Auth::guard('web')->login($user, $remember); $request->session()->regenerate(); $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $events->record($request, 'auth.mfa_verified', $user->id); $events->record($request, $mode === 'platform' ? 'auth.platform_login_succeeded' : 'auth.business_login_succeeded', $user->id); if ($newDevice) $events->record($request, 'auth.new_device', $user->id, null, ['device_hash' => hash('sha256', (string) $request->userAgent())]);
        return response()->json(['data' => $user->only(['id', 'name', 'email', 'status']), 'workspace_access_required' => $mode === 'business' && ! $user->tenantMemberships()->where('status', 'active')->exists()]);
    }
    public function disable(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['password' => ['required', 'string'], 'code' => ['nullable', 'string']]); abort_unless(Hash::check($data['password'], $request->user()->password), 422, 'Password confirmation failed.'); if ($request->user()->mfa_enabled_at) abort_unless(! empty($data['code']) && $mfa->verify($mfa->decrypt($request->user()) ?? '', $data['code']), 422, 'MFA confirmation failed.'); $request->user()->update(['mfa_secret_encrypted' => null, 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null]); $request->session()->forget('security.step_up_until'); DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete(); $events->record($request, 'auth.mfa_disabled'); return response()->json(['status' => 'ok']); }
    public function regenerateRecoveryCodes(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse { abort_unless($request->user()->mfa_enabled_at, 422, 'MFA is not enabled.'); $codes = $mfa->recoveryCodes(); $request->user()->update(['mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes)]); $request->session()->forget('security.step_up_until'); $events->record($request, 'auth.mfa_recovery_codes_regenerated'); return response()->json(['recovery_codes' => $codes]); }
}
