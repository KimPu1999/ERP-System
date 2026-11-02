<?php

namespace App\Http\Controllers\Api\V1;

use App\Mail\PlatformAdminInvitationMail;
use App\Models\{PlatformAdminInvitation, User};
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class PlatformAdminInvitationController
{
    public function index(): JsonResponse
    {
        $rows = PlatformAdminInvitation::query()
            ->with('inviter:id,name,email')
            ->latest()
            ->paginate(min(100, max(10, (int) request()->query('per_page', 25))));

        return response()->json(['data' => $rows->through(fn (PlatformAdminInvitation $invitation) => $this->payload($invitation))]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:14'],
        ]);
        $email = strtolower(trim($data['email']));

        abort_if(
            PlatformAdminInvitation::where('email', $email)->where('status', 'pending')->where('expires_at', '>', now())->exists(),
            409,
            'A pending platform administrator invitation already exists for this email.',
        );
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
        abort_if($existing, 409, 'An account already exists for this email. Use the existing account management or CLI provisioning flow.');

        $token = Str::random(64);
        $invitation = PlatformAdminInvitation::create([
            'inviter_user_id' => $request->user()->id,
            'email' => $email,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->send(new PlatformAdminInvitationMail($invitation, $token));
        } catch (\Throwable) {
            $audit->record($request, 'platform.admin_invitation.delivery_failed', null, $invitation);
            return response()->json(['message' => 'The invitation was created, but the configured mail provider could not accept it.', 'invitation_id' => $invitation->id], 503);
        }

        $audit->record($request, 'platform.admin_invitation.created', null, $invitation, ['email' => $email]);
        return response()->json(['data' => $this->payload($invitation->fresh('inviter'))], 201);
    }

    public function resend(Request $request, PlatformAdminInvitation $invitation, AuditLogger $audit): JsonResponse
    {
        abort_if(in_array($invitation->status, ['accepted', 'revoked'], true), 422, 'This invitation cannot be resent.');
        $token = Str::random(64);
        $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'status' => 'pending', 'revoked_at' => null]);
        try {
            Mail::to($invitation->email)->send(new PlatformAdminInvitationMail($invitation, $token));
        } catch (\Throwable) {
            $audit->record($request, 'platform.admin_invitation.delivery_failed', null, $invitation);
            return response()->json(['message' => 'The configured mail provider could not accept the invitation.'], 503);
        }
        $audit->record($request, 'platform.admin_invitation.resent', null, $invitation);
        return response()->json(['data' => $this->payload($invitation->fresh('inviter'))]);
    }

    public function revoke(Request $request, PlatformAdminInvitation $invitation, AuditLogger $audit): JsonResponse
    {
        abort_if($invitation->status !== 'pending', 422, 'Only pending invitations can be revoked.');
        $invitation->update(['status' => 'revoked', 'revoked_at' => now(), 'token_hash' => hash('sha256', Str::random(64))]);
        $audit->record($request, 'platform.admin_invitation.revoked', null, $invitation);
        return response()->json(['data' => $this->payload($invitation->fresh('inviter'))]);
    }

    public function accept(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $tokenHash = hash('sha256', $data['token']);

        $user = DB::transaction(function () use ($data, $tokenHash, $audit, $request): User {
            $invitation = PlatformAdminInvitation::where('token_hash', $tokenHash)->lockForUpdate()->firstOrFail();
            abort_if($invitation->status !== 'pending' || $invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'This platform invitation is no longer valid.');
            $existing = User::whereRaw('LOWER(email) = ?', [$invitation->email])->lockForUpdate()->first();
            abort_if($existing, 409, 'An account already exists for this email. Sign in or ask the platform administrator to manage the existing account.');

            $created = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'status' => 'active',
                'email_verified_at' => now(),
                'is_platform_super_admin' => true,
            ]);
            $invitation->update(['status' => 'accepted', 'accepted_at' => now(), 'token_hash' => hash('sha256', Str::random(64))]);
            $audit->record($request, 'platform.admin_invitation.accepted', null, $created, ['invitation_id' => $invitation->id]);
            return $created;
        });

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'status']), 'email_verified' => true], 201);
    }

    private function payload(PlatformAdminInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'status' => $invitation->status,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
            'created_at' => $invitation->created_at?->toISOString(),
            'inviter' => $invitation->inviter?->only(['id', 'name', 'email']),
        ];
    }
}
