<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\MfaService;
use App\Services\SecurityEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class MfaController
{
    public function enroll(Request $request, MfaService $mfa): JsonResponse { $secret = $mfa->generateSecret(); $request->session()->put('mfa.pending_secret', $secret); return response()->json(['secret' => $secret, 'otpauth_uri' => $mfa->provisioningUri($request->user(), $secret)]); }
    public function confirm(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['code' => ['required', 'digits:6']]); $secret = $request->session()->pull('mfa.pending_secret'); abort_unless($secret && $mfa->verify($secret, $data['code']), 422, 'Invalid MFA code.'); $codes = $mfa->recoveryCodes(); $request->user()->update(['mfa_secret_encrypted' => $mfa->encrypt($secret), 'mfa_enabled_at' => now(), 'mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes)]); $events->record($request, 'auth.mfa_enabled'); return response()->json(['recovery_codes' => $codes]); }
    public function verifyLogin(Request $request, MfaService $mfa, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['code' => ['required', 'string']]); $user = \App\Models\User::find($request->session()->get('auth.mfa_user_id')); abort_unless($user, 401, 'MFA challenge expired.'); $valid = $mfa->verify($mfa->decrypt($user) ?? '', $data['code']) || $mfa->consumeRecoveryCode($user, $data['code']); abort_unless($valid, 422, 'Invalid MFA code.'); $remember = (bool) $request->session()->pull('auth.mfa_remember', false); $request->session()->forget('auth.mfa_user_id'); Auth::login($user, $remember); $request->session()->regenerate(); $events->record($request, 'auth.mfa_verified', $user->id); return response()->json(['data' => $user->only(['id', 'name', 'email', 'status'])]); }
    public function disable(Request $request, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['password' => ['required', 'string']]); abort_unless(Hash::check($data['password'], $request->user()->password), 422, 'Password confirmation failed.'); $request->user()->update(['mfa_secret_encrypted' => null, 'mfa_enabled_at' => null, 'mfa_recovery_codes' => null]); $events->record($request, 'auth.mfa_disabled'); return response()->json(['status' => 'ok']); }
}
