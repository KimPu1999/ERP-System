<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\SecurityEventLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

final class PasswordResetController
{
    public function requestLink(Request $request, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['email' => ['required', 'email']]); $status = Password::sendResetLink(['email' => $data['email']]); $events->record($request, 'auth.password_reset_requested', null, null, ['email' => $data['email']]); return response()->json(['status' => __($status)]); }
    public function reset(Request $request, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]); $status = Password::reset($data, function (User $user, string $password) use ($events, $request): void { $user->forceFill(['password' => Hash::make($password), 'remember_token' => null])->save(); event(new PasswordReset($user)); $events->record($request, 'auth.password_reset_completed', $user->id); }); return $status === Password::PASSWORD_RESET ? response()->json(['status' => __($status)]) : response()->json(['message' => __($status)], 422); }
}
