<?php

use App\Models\{PlatformAuditLog, SupportAttachment, SupportSlaPolicy, SupportTicket, Tenant, TenantMembership, User};
use Illuminate\Http\UploadedFile;

function supportFixture(): array
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $userA->id, 'status' => 'active']);
    TenantMembership::create(['tenant_id' => $tenantB->id, 'user_id' => $userB->id, 'status' => 'active']);
    return [$userA, $userB, $tenantA, $tenantB];
}

it('creates a ticket with a unique ticket number and the documented lifecycle defaults', function (): void {
    [$user, , $tenantA] = supportFixture();
    $response = $this->actingAs($user)->postJson('/api/v1/support/tickets', [
        'subject' => 'Warehouse sync broken', 'description' => 'Stock counts stop syncing after saving.', 'priority' => 'high', 'category' => 'inventory',
    ], ['X-Tenant-Id' => $tenantA->id])->assertCreated();

    $payload = $response->json('data');
    expect($payload['status'])->toBe('open')
        ->and($payload['ticket_number'])->toMatch('/^TKT-\d{8}-[0-9A-F]{8}$/i')
        ->and($payload['category'])->toBe('inventory')
        ->and($payload['reopenable'])->toBeFalse();
    expect(SupportTicket::where('ticket_number', $payload['ticket_number'])->count())->toBe(1);
});

it('blocks a rejected attachment type and legal mime allowlist is enforced', function (): void {
    [$user, , $tenantA] = supportFixture();
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000001', 'created_by' => $user->id, 'subject' => 's', 'description' => 'd', 'status' => 'open']);
    $this->actingAs($user)->postJson("/api/v1/support/tickets/{$ticket->id}/reply", [
        'body' => 'here is my file',
        'attachments' => [UploadedFile::fake()->create('payload.exe', 5, 'application/x-msdownload')],
    ], ['X-Tenant-Id' => $tenantA->id])->assertStatus(422);
    expect(SupportAttachment::where('ticket_id', $ticket->id)->exists())->toBeFalse();
});

it('authorizes attachment downloads inside the tenant and rejects cross-tenant access', function (): void {
    [$userA, , $tenantA] = supportFixture();
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000002', 'created_by' => $userA->id, 'subject' => 's', 'description' => 'd', 'status' => 'open']);
    $path = UploadedFile::fake()->createWithContent('note.txt', 'hello')->store('support-attachments', 'local');
    $attachment = SupportAttachment::create(['tenant_id' => $tenantA->id, 'ticket_id' => $ticket->id, 'uploaded_by' => $userA->id, 'disk' => 'local', 'path' => $path, 'original_name' => 'note.txt', 'mime_type' => 'text/plain', 'size_bytes' => 5]);
    $this->actingAs($userA)->getJson("/api/v1/support/tickets/{$ticket->id}/attachments/{$attachment->id}", ['X-Tenant-Id' => $tenantA->id])->assertOk();
});

it('prevents tenant A from reading or replying to tenant B tickets', function (): void {
    [$userA, $userB, $tenantA, $tenantB] = supportFixture();
    $ticketB = SupportTicket::create(['tenant_id' => $tenantB->id, 'ticket_number' => 'TKT-TEST-00000003', 'created_by' => $userB->id, 'subject' => 'Tenant B issue', 'description' => 'Tenant B confidential', 'status' => 'open']);
    $headers = ['X-Tenant-Id' => $tenantA->id];
    $this->actingAs($userA)->getJson('/api/v1/support/tickets', $headers)->assertOk();
    expect($this->actingAs($userA)->getJson('/api/v1/support/tickets', $headers)->json('data'))->toBeEmpty();
    $this->actingAs($userA)->getJson("/api/v1/support/tickets/{$ticketB->id}", $headers)->assertNotFound();
    $this->actingAs($userA)->postJson("/api/v1/support/tickets/{$ticketB->id}/reply", ['body' => 'intrusion'], $headers)->assertNotFound();
    $this->actingAs($userA)->postJson("/api/v1/support/tickets/{$ticketB->id}/close", [], $headers)->assertNotFound();
});

it('enforces valid status transitions and records resolution notes before resolving', function (): void {
    [$user, , $tenantA] = supportFixture();
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000004', 'created_by' => $user->id, 'subject' => 's', 'description' => 'd', 'status' => 'open']);
    $headers = ['X-Tenant-Id' => $tenantA->id];
    $this->actingAs($user)->postJson("/api/v1/support/tickets/{$ticket->id}/reply", ['body' => 'Awaiting updates'], $headers);
    // closed tickets cannot transition to in_progress
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->patchJson("/api/v1/platform/support/tickets/{$ticket->id}", ['status' => 'closed'])->assertOk();
    expect($ticket->refresh()->closed_at)->not->toBeNull();
    $this->actingAs($platform)->patchJson("/api/v1/platform/support/tickets/{$ticket->id}", ['status' => 'in_progress'])->assertStatus(422);
});

it('keeps internal support notes private from the business thread', function (): void {
    [$userA, , $tenantA] = supportFixture();
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000005', 'created_by' => $userA->id, 'subject' => 's', 'description' => 'd', 'status' => 'open']);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->postJson("/api/v1/platform/support/tickets/{$ticket->id}/reply", ['body' => 'Internal: check payment gateway logs', 'visibility' => 'internal'])->assertCreated();
    $businessView = $this->actingAs($userA)->getJson("/api/v1/support/tickets/{$ticket->id}", ['X-Tenant-Id' => $tenantA->id])->assertOk()->json('data');
    expect(collect($businessView['thread'])->contains(fn ($m) => $m['visibility'] === 'internal'))->toBeFalse()
        ->and(collect($businessView['thread'])->contains(fn ($m) => str_contains((string) $m['body'], 'payment gateway')))->toBeFalse();
    $platformView = $this->actingAs($platform)->getJson("/api/v1/platform/support/tickets/{$ticket->id}")->assertOk()->json('data');
    expect(collect($platformView['thread'])->contains(fn ($m) => $m['visibility'] === 'internal'))->toBeTrue();
});

it('records first response automatically on the first public platform reply and assignments are audited', function (): void {
    [$user, , $tenantA] = supportFixture();
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000006', 'created_by' => $user->id, 'subject' => 's', 'description' => 'd', 'status' => 'open']);
    $agent = User::factory()->create();
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->patchJson("/api/v1/platform/support/tickets/{$ticket->id}", ['assigned_to' => $agent->id])->assertOk();
    $this->actingAs($platform)->postJson("/api/v1/platform/support/tickets/{$ticket->id}/reply", ['body' => 'We are on it.'])->assertCreated();
    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($agent->id)
        ->and($ticket->first_response_at)->not->toBeNull()
        ->and($ticket->status)->toBe('in_progress')
        ->and(PlatformAuditLog::where('auditable_id', $ticket->id)->where('action', 'platform.support.ticket.updated')->exists())->toBeTrue();
});

it('computes SLA status from the active policy and minutes targets including business hours', function (): void {
    [$user, , $tenantA] = supportFixture();
    SupportSlaPolicy::create(['name' => 'High priority', 'priority' => 'high', 'first_response_minutes' => 60, 'resolution_minutes' => 480, 'is_active' => true]);
    $ticket = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000007', 'created_by' => $user->id, 'subject' => 's', 'description' => 'd', 'priority' => 'high', 'status' => 'open', 'sla_policy_id' => SupportSlaPolicy::where('priority', 'high')->first()->id, 'first_response_due_at' => now()->addMinutes(60), 'resolution_due_at' => now()->addMinutes(480)]);
    $payload = $this->actingAs($user)->getJson("/api/v1/support/tickets/{$ticket->id}", ['X-Tenant-Id' => $tenantA->id])->assertOk()->json('data');
    expect($payload['sla_status'])->toBe('on_track');
    $overdue = SupportTicket::create(['tenant_id' => $tenantA->id, 'ticket_number' => 'TKT-TEST-00000008', 'created_by' => $user->id, 'subject' => 's', 'description' => 'd', 'priority' => 'urgent', 'status' => 'open', 'first_response_due_at' => now()->subHour(), 'resolution_due_at' => now()->subMinutes(50)]);
    expect($this->actingAs($user)->getJson("/api/v1/support/tickets/{$overdue->id}", ['X-Tenant-Id' => $tenantA->id])->assertOk()->json('data.sla_status'))->toBe('breached');
    // New urgent ticket without a configured policy reports not_configured.
    SupportSlaPolicy::whereNull('priority')->delete();
    $noPolicy = $this->actingAs($user)->postJson('/api/v1/support/tickets', ['subject' => 'n', 'description' => 'd'], ['X-Tenant-Id' => $tenantA->id])->assertCreated()->json('data');
    expect($noPolicy['first_response_due_at'])->toBeNull();
});

it('protects platform support APIs behind platform.superadmin middleware', function (): void {
    [$userA, , $tenantA] = supportFixture();
    $this->getJson('/api/v1/platform/support/tickets')->assertUnauthorized();
    $this->actingAs($userA)->getJson('/api/v1/platform/support/tickets')->assertForbidden();
    $this->actingAs($userA)->getJson('/api/v1/platform/operations/overview', ['X-Tenant-Id' => $tenantA->id])->assertForbidden();
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->getJson('/api/v1/platform/support/tickets')->assertOk();
});
