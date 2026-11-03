<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Services\SecurityEventLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

final class PasswordResetController
{
    public function requestLink(Request $request, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['email' => ['required', 'email']]); $status = Password::sendResetLink(['email' => $data['email']]); $events->record($request, 'auth.password_reset_requested', null, null, ['email_hash' => hash('sha256', strtolower($data['email']))]); return response()->json(['status' => __($status)]); }
    public function reset(Request $request, SecurityEventLogger $events): JsonResponse { $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]); $status = Password::reset($data, function (User $user, string $password) use ($events, $request): void { $user->forceFill(['password' => Hash::make($password), 'remember_token' => null])->save(); DB::table('sessions')->where('user_id', $user->id)->delete(); DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->where('tokenable_type', User::class)->delete(); event(new PasswordReset($user)); $events->record($request, 'auth.password_reset_completed', $user->id); }); return $status === Password::PASSWORD_RESET ? response()->json(['status' => __($status)]) : response()->json(['message' => __($status)], 422); }
}
