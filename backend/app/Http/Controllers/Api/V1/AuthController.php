<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\{EmailVerificationDeliveryService, SecurityEventLogger, SettingsService};
use App\Services\SecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class AuthController
{
    public function register(Request $request, SecurityEventLogger $events, SettingsService $settings, EmailVerificationDeliveryService $delivery): JsonResponse
    {
        abort_unless((bool) $settings->getForPolicy('business')['public_registration_enabled'], 403, 'Public business registration is currently disabled.');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = DB::transaction(fn () => User::create($data));
        $verificationDelivery = $delivery->send($user);
        if (! in_array($verificationDelivery['status'], ['accepted_by_configured_mailer', 'queued'], true)) {
            return response()->json([
                'message' => 'Account created, but the verification message could not be delivered. Configure email delivery and request a new link.',
                'email_verification_required' => true,
                'verification_delivery' => ['status' => $verificationDelivery['status']],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        Auth::guard('web')->login($user); $request->session()->regenerate();
        $events->record($request, 'auth.registered', $user->id);
        return response()->json(['data' => $user->only(['id', 'name', 'email', 'status']), 'email_verification_required' => true, 'verification_delivery' => $verificationDelivery], 201);
    }

    public function login(Request $request, SecurityEventLogger $events, SettingsService $settings, SecurityPolicy $policy): JsonResponse
    {
        return $this->authenticate($request, $events, 'business', $policy, $settings);
    }

    public function businessLogin(Request $request, SecurityEventLogger $events, SettingsService $settings, SecurityPolicy $policy): JsonResponse
    {
        return $this->authenticate($request, $events, 'business', $policy, $settings);
    }

    public function platformLogin(Request $request, SecurityEventLogger $events, SettingsService $settings, SecurityPolicy $policy): JsonResponse
    {
        return $this->authenticate($request, $events, 'platform', $policy, $settings);
    }

    private function authenticate(Request $request, SecurityEventLogger $events, string $mode, SecurityPolicy $policy, SettingsService $settings): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) { $events->record($request, 'auth.login_failed', $user?->id, null, ['reason' => 'invalid_credentials']); throw ValidationException::withMessages(['email' => [__('auth.failed')]]); }
        if ($user->status !== 'active' || $user->suspended_at) { $events->record($request, 'auth.login_blocked', $user->id, null, ['reason' => 'account_suspended']); abort(423, __('auth.suspended')); }
        $verificationRequired = $mode === 'platform' || (bool) $settings->getForPolicy('business')['require_email_verification'];
        if ($verificationRequired && ! $user->hasVerifiedEmail()) { $events->record($request, 'auth.login_blocked', $user->id, null, ['reason' => 'email_unverified', 'mode' => $mode]); abort(403, 'Email verification is required before sign-in.'); }

        $hasActiveMembership = false;
        if ($mode === 'platform') {
            abort_unless($user->is_platform_super_admin === true, 403, 'Platform administrator access required.');
        } else {
            abort_if($user->is_platform_super_admin === true, 403, 'Use the platform administrator sign-in.');

            $hasActiveMembership = $user->tenantMemberships()->where('status', 'active')->exists();
            $hasPendingInvitation = ! $hasActiveMembership && $user->invitations()
                ->where('status', 'pending')
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->exists();

            abort_unless($hasActiveMembership || $hasPendingInvitation, 403, 'An active business workspace membership is required.');
        }

        if ($user->mfa_enabled_at) {
            $request->session()->put([
                'auth.mfa_user_id' => $user->id,
                'auth.mfa_remember' => (bool) ($data['remember'] ?? false),
                'auth.mfa_mode' => $mode,
                'auth.login_pending' => true,
                'auth.new_device' => $this->isNewDevice($user, $request),
            ]);
            return response()->json(['mfa_required' => true], 202);
        }

        $mfaRequired = $mode === 'platform'
            ? $policy->platformMfaRequired()
            : $policy->businessAdminMfaRequired() && $policy->isBusinessAdministrator($user);

        if ($mfaRequired) {
            $newDevice = $this->isNewDevice($user, $request);
            Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $request->session()->put(['auth.login_pending' => true, 'auth.new_device' => $newDevice, 'auth.mfa_mode' => $mode]);
            $events->record($request, 'auth.mfa_enrollment_required', $user->id, null, ['mode' => $mode]);
            return response()->json(['mfa_enrollment_required' => true, 'mfa_mode' => $mode], 202);
        }

        $newDevice = $this->isNewDevice($user, $request);
        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $events->record($request, $mode === 'platform' ? 'auth.platform_login_succeeded' : 'auth.business_login_succeeded', $user->id);
        if ($newDevice) $events->record($request, 'auth.new_device', $user->id, null, ['device_hash' => hash('sha256', (string) $request->userAgent())]);

        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'status']),
            'workspace_access_required' => $mode === 'business' && ! $hasActiveMembership,
        ]);
    }

    public function logout(Request $request, SecurityEventLogger $events): JsonResponse
    {
        $userId = $request->user()?->id; Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); $events->record($request, 'auth.logout', $userId); return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse { return response()->json(['data' => $request->user()->only(['id', 'name', 'email', 'status', 'email_verified_at', 'mfa_enabled_at', 'is_platform_super_admin'])]); }

    private function isNewDevice(User $user, Request $request): bool
    {
        $userAgent = trim((string) $request->userAgent());
        return $userAgent !== '' && ! DB::table('sessions')->where('user_id', $user->id)->where('user_agent', $userAgent)->exists();
    }
}
