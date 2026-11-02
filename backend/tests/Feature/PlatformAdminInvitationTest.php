<?php

use App\Mail\PlatformAdminInvitationMail;
use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('allows a verified platform administrator to create a single-use platform invitation', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['is_platform_super_admin' => true]);

    $response = $this->actingAs($admin)->postJson('/api/v1/platform/invitations', [
        'email' => 'new-platform-admin@example.test',
    ]);

    $response->assertCreated()->assertJsonPath('data.email', 'new-platform-admin@example.test');
    expect(PlatformAdminInvitation::where('email', 'new-platform-admin@example.test')->exists())->toBeTrue();
    Mail::assertSent(PlatformAdminInvitationMail::class);
});

it('rejects platform invitation management for business users', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/platform/invitations')->assertForbidden();
    $this->actingAs($user)->postJson('/api/v1/platform/invitations', ['email' => 'admin@example.test'])->assertForbidden();
    Mail::assertNothingSent();
});

it('accepts a platform invitation once and creates a verified platform administrator', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['is_platform_super_admin' => true]);

    $this->actingAs($admin)->postJson('/api/v1/platform/invitations', ['email' => 'new-platform-admin@example.test'])->assertCreated();
    $mail = Mail::sent(PlatformAdminInvitationMail::class)->first();
    expect($mail)->not->toBeNull();

    $response = $this->postJson('/api/v1/auth/platform/invitations/accept', [
        'token' => $mail->token,
        'name' => 'New Platform Admin',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertCreated()->assertJsonPath('email_verified', true);
    $created = User::where('email', 'new-platform-admin@example.test')->firstOrFail();
    expect($created->is_platform_super_admin)->toBeTrue()->and($created->hasVerifiedEmail())->toBeTrue();
    $this->postJson('/api/v1/auth/platform/invitations/accept', [
        'token' => $mail->token,
        'name' => 'Duplicate',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertNotFound();
});

it('does not accept an expired platform invitation', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($admin)->postJson('/api/v1/platform/invitations', ['email' => 'expired-platform-admin@example.test'])->assertCreated();
    $mail = Mail::sent(PlatformAdminInvitationMail::class)->first();
    PlatformAdminInvitation::where('email', 'expired-platform-admin@example.test')->update(['expires_at' => now()->subMinute()]);

    $this->postJson('/api/v1/auth/platform/invitations/accept', [
        'token' => $mail->token,
        'name' => 'Expired',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertGone();
});
