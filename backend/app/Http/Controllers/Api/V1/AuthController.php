<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\SecurityEventLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function register(Request $request, SecurityEventLogger $events): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $user = DB::transaction(fn () => User::create($data));
        event(new Registered($user));
        Auth::login($user); $request->session()->regenerate();
        $events->record($request, 'auth.registered', $user->id);
        return response()->json(['data' => $user->only(['id', 'name', 'email', 'status']), 'email_verification_required' => true], 201);
    }

    public function login(Request $request, SecurityEventLogger $events): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) { $events->record($request, 'auth.login_failed', $user?->id, null, ['reason' => 'invalid_credentials']); throw ValidationException::withMessages(['email' => ['The credentials are invalid.']]); }
        if ($user->status !== 'active' || $user->suspended_at) { $events->record($request, 'auth.login_blocked', $user->id, null, ['reason' => 'account_suspended']); abort(423, 'Account is suspended.'); }
        if ($user->mfa_enabled_at) { $request->session()->put(['auth.mfa_user_id' => $user->id, 'auth.mfa_remember' => (bool) ($data['remember'] ?? false)]); return response()->json(['mfa_required' => true], 202); }
        Auth::login($user, (bool) ($data['remember'] ?? false)); $request->session()->regenerate(); $events->record($request, 'auth.login_succeeded', $user->id);
        return response()->json(['data' => $user->only(['id', 'name', 'email', 'status'])]);
    }

    public function logout(Request $request, SecurityEventLogger $events): JsonResponse
    {
        $userId = $request->user()?->id; Auth::guard('web')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); $events->record($request, 'auth.logout', $userId); return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse { return response()->json(['data' => $request->user()->only(['id', 'name', 'email', 'status', 'email_verified_at', 'mfa_enabled_at', 'is_platform_super_admin'])]); }
}
