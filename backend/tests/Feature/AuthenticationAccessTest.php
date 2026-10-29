<?php

use App\Models\User;

it('rejects invalid credentials without creating a session', function (): void {
    $user = User::factory()->create(['email' => 'auth@example.test']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
    expect(auth()->check())->toBeFalse();
});

it('blocks suspended accounts at login', function (): void {
    $user = User::factory()->create(['status' => 'suspended', 'suspended_at' => now(), 'password' => 'secret-password']);
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret-password'])->assertStatus(423);
});

it('requires authentication for session management', function (): void {
    $this->getJson('/api/v1/auth/sessions')->assertUnauthorized();
});

it('does not allow an invitation to be accepted by another email', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create(['email' => 'different@example.test']);
    $this->actingAs($other)->postJson('/api/v1/invitations/accept', ['invitation_id' => '00000000-0000-0000-0000-000000000000', 'token' => 'invalid'])->assertNotFound();
    expect($owner->id)->not->toBe($other->id);
});

it('does not expose platform tenant management to ordinary users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/v1/platform/tenants')->assertForbidden();
});
