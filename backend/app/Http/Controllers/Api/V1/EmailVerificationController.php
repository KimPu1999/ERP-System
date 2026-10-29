<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SecurityEventLogger;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailVerificationController
{
    public function verify(Request $request, SecurityEventLogger $events): JsonResponse { $user = User::findOrFail($request->route('id')); abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash')), 403, 'Invalid verification link.'); if ($user->hasVerifiedEmail()) return response()->json(['status' => 'already_verified']); $user->markEmailAsVerified(); event(new Verified($user)); $events->record($request, 'auth.email_verified', $user->id); return response()->json(['status' => 'verified']); }
    public function resend(Request $request, SecurityEventLogger $events): JsonResponse { abort_if($request->user()->hasVerifiedEmail(), 409, 'Email is already verified.'); $request->user()->sendEmailVerificationNotification(); $events->record($request, 'auth.verification_resent'); return response()->json(['status' => 'sent']); }
}
