<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\SecurityEventLogger;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class EmailVerificationController
{
    public function verify(Request $request, SecurityEventLogger $events): JsonResponse|RedirectResponse
    {
        // Keep the signed-link requirement in the controller so browser clicks can
        // receive the same safe result page as API clients, including invalid and
        // expired links. No user lookup is performed until the signature is valid.
        if (! $request->hasValidSignature()) {
            return $this->verificationResponse($request, ['message' => 'Invalid or expired verification link.'], 'invalid', 403);
        }

        $user = User::find($request->route('id'));
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return $this->verificationResponse($request, ['message' => 'Invalid or expired verification link.'], 'invalid', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->verificationResponse($request, ['status' => 'already_verified'], 'already_verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
        $events->record($request, 'auth.email_verified', $user->id);

        return $this->verificationResponse($request, ['status' => 'verified'], 'verified');
    }

    public function resend(Request $request, SecurityEventLogger $events): JsonResponse
    {
        abort_if($request->user()->hasVerifiedEmail(), 409, 'Email is already verified.');
        $request->user()->sendEmailVerificationNotification();
        $events->record($request, 'auth.verification_resent');

        return response()->json(['status' => 'sent']);
    }

    private function verificationResponse(Request $request, array $payload, string $frontendStatus, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json($payload, $status);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query(['status' => $frontendStatus]);

        return redirect()->away($frontendUrl.'/verify-email?'.$query);
    }
}
