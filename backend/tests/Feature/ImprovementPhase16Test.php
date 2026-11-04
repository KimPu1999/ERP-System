<?php

use App\Models\{
    DefectRecord, FeedbackNote, FeatureFlag, FeatureRequest, FeatureRollout, MaturityAssessment,
    MonitoringObservation, PlatformAuditLog, ProductFeedback, ReleaseRecord, RoadmapItem,
    SatisfactionResponse, ServiceLevelObjective, Tenant, TenantFeatureFlag, TenantMembership, User,
};
use Illuminate\Http\Testing\File;

function improvementPlatformUser(): User
{
    return User::factory()->create(['is_platform_super_admin' => true]);
}

/** Returns [user, tenant] with an active membership. */
function improvementBusinessUser(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    return [$user, $tenant];
}

function improvementSubmit(User $user, Tenant $tenant, array $overrides = []): ProductFeedback
{
    $payload = array_merge([
        'category' => 'usability', 'subject' => 'Stock list is confusing', 'message' => 'Columns need tooltips.',
        'module' => 'inventory',
    ], $overrides);
    return ProductFeedback::create([
        'tenant_id' => $tenant->id, 'feedback_number' => 'FB-T-'.bin2hex(random_bytes(4)),
        'category' => $payload['category'], 'module' => $payload['module'], 'subject' => $payload['subject'],
        'message' => $payload['message'], 'submitted_by' => $user->id,
        'content_hash' => hash('sha256', implode('|', [$tenant->id, $payload['category'], strtolower(trim($payload['subject']))])),
    ]);
}

it('lets a super admin open the Improvement Center overview with explicit unmeasured areas', function (): void {
    $platform = improvementPlatformUser();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/overview')->assertOk()->json('data');
    expect($data['last_updated_at'])->toBeString()
        ->and($data['adoption_summary']['source'])->toBe('phase_13_growth_analytics')
        ->and($data['customer_satisfaction']['state'])->toBe('unknown')
        ->and($data['current_release'])->toBeNull();
});

it('blocks business users from all internal improvement endpoints', function (): void {
    [$user] = improvementBusinessUser();
    $this->actingAs($user)->getJson('/api/v1/platform/improvement/overview')->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/platform/improvement/feedback')->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/platform/improvement/maturity')->assertForbidden();
});

it('submits business feedback and never exposes other tenants feedback', function (): void {
    [$userA, $tenantA] = improvementBusinessUser();
    [$userB, $tenantB] = improvementBusinessUser();

    $this->actingAs($userA)->withHeaders(['X-Tenant-Id' => $tenantA->id])
        ->postJson('/api/v1/business/feedback', ['category' => 'bug', 'subject' => 'Invoice rounding error', 'message' => 'Totals are off by cents.', 'module' => 'accounting'])
        ->assertStatus(201);
    $feedbackA = ProductFeedback::first();

    fwrite(STDERR, "\nMARK-getA\n");
    $this->actingAs($userA)->withHeaders(['X-Tenant-Id' => $tenantA->id])->getJson("/api/v1/business/feedback/{$feedbackA->id}")->assertOk();
    fwrite(STDERR, "MARK-getB\n");
    $this->actingAs($userB)->withHeaders(['X-Tenant-Id' => $tenantB->id])->getJson("/api/v1/business/feedback/{$feedbackA->id}")->assertNotFound();

    $listB = $this->actingAs($userB)->withHeaders(['X-Tenant-Id' => $tenantB->id])->getJson('/api/v1/business/feedback')->json('data');
    expect($listB)->toBeEmpty();
    expect(ProductFeedback::where('tenant_id', $tenantB->id)->count())->toBe(0);

    $platformList = $this->actingAs(improvementPlatformUser())->getJson('/api/v1/platform/improvement/feedback')->json('data');
    expect(count($platformList))->toBe(1)->and($platformList[0]['tenant']['id'])->toBe($tenantA->id);
});

it('validates feedback attachments strictly', function (): void {
    [$user, $tenant] = improvementBusinessUser();
    $url = '/api/v1/business/feedback';

    // Permitted upload: small text file is accepted and recorded.
    $upload = Illuminate\Http\UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
    $ok = $this->actingAs($user)->withHeaders(['X-Tenant-Id' => $tenant->id])
        ->post($url, ['category' => 'bug', 'subject' => 'With valid attachment', 'message' => 'File enclosed.', 'attachment' => $upload]);
    $ok->assertStatus(201);
    $feedback = ProductFeedback::where('tenant_id', $tenant->id)->first();
    expect($feedback->attachments)->toHaveCount(1)
        ->and(Illuminate\Support\Facades\Storage::disk('local')->exists($feedback->attachments[0]['path']))->toBeTrue();

    // Forbidden mime is rejected outright.
    $badUpload = Illuminate\Http\UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');
    $this->actingAs($user)->withHeaders(['X-Tenant-Id' => $tenant->id])
        ->post($url, ['category' => 'bug', 'subject' => 'bad attachment', 'message' => 'x', 'attachment' => $badUpload])
        ->assertStatus(422);
    expect(ProductFeedback::where('subject', 'bad attachment')->exists())->toBeFalse();

    // Over-size upload (6 MB) is rejected.
    $huge = Illuminate\Http\UploadedFile::fake()->create('big.pdf', 6144, 'application/pdf');
    $this->actingAs($user)->withHeaders(['X-Tenant-Id' => $tenant->id])
        ->post($url, ['category' => 'bug', 'subject' => 'big attachment', 'message' => 'x', 'attachment' => $huge])
        ->assertStatus(422);
});

it('keeps internal platform notes private from the business thread', function (): void {
    [$user, $tenant] = improvementBusinessUser();
    $feedback = improvementSubmit($user, $tenant, ['subject' => 'internal note isolation', 'message' => 'm', 'category' => 'bug']);
    FeedbackNote::create(['tenant_id' => $tenant->id, 'feedback_id' => $feedback->id, 'author_type' => 'platform', 'visibility' => 'internal', 'body' => 'Internal root-cause speculation', 'created_at' => now()]);
    FeedbackNote::create(['tenant_id' => $tenant->id, 'feedback_id' => $feedback->id, 'author_type' => 'platform', 'visibility' => 'public', 'body' => 'We published an explanation', 'created_at' => now()]);

    $own = $this->actingAs($user)->withHeaders(['X-Tenant-Id' => $tenant->id])->getJson("/api/v1/business/feedback/{$feedback->id}")->json('data');
    expect(collect($own['notes'])->pluck('body')->all())->toBe(['We published an explanation']);

    $view = $this->actingAs(improvementPlatformUser())->getJson("/api/v1/platform/improvement/feedback/{$feedback->id}")->assertOk()->json('data');
    expect(count($view['notes']))->toBe(2);
});

it('handles duplicate feedback safely with audited closure instead of data loss', function (): void {
    [$user, $tenant] = improvementBusinessUser();
    $platform = improvementPlatformUser();
    $feedback = improvementSubmit($user, $tenant, ['subject' => 'dup subject', 'category' => 'bug']);
    $duplicate = improvementSubmit($user, $tenant, ['subject' => 'dup subject', 'category' => 'bug']);

    expect($duplicate->content_hash)->toBe($feedback->content_hash);

    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/feedback/{$duplicate->id}", ['duplicate_of_id' => $feedback->id])->assertOk();
    $show = $this->actingAs($platform)->getJson("/api/v1/platform/improvement/feedback/{$duplicate->id}")->assertOk()->json('data');
    expect($show['duplicate_of']['id'])->toBe($feedback->id)
        ->and(collect($show['history'])->pluck('to_status'))->toContain('closed');

    // arbitrary status editing is refused after closure
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/feedback/{$duplicate->id}", ['status' => 'under_review'])->assertUnprocessable();
});

it('authorizes feature request workflow transitions and never reports invented estimates', function (): void {
    $platform = improvementPlatformUser();
    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/requests', [
        'title' => 'Add warehouse transfer report', 'description' => 'Cross-warehouse transfer summary.',
        'module' => 'inventory', 'priority' => 'medium', 'effort_estimate' => 'not yet', 'effort_estimated' => false,
    ])->assertStatus(201);

    $request = FeatureRequest::first();
    expect($request->status)->toBe('new')->and($request->effort_estimate)->toBeNull();

    // Unjustified jump refused.
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/requests/{$request->id}", ['status' => 'released'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/requests/{$request->id}", ['status' => 'triaged'])->assertOk();

    $show = $this->actingAs($platform)->getJson("/api/v1/platform/improvement/requests/{$request->id}")->assertOk()->json('data');
    expect($show['possible_transitions'])->toContain('planned')
        ->and(collect($show['events'])->pluck('event'))->toContain('status_changed');
});

it('records roadmap changes in the platform audit log and keeps confidential items unpublished', function (): void {
    $platform = improvementPlatformUser();
    [$user, $tenant] = improvementBusinessUser();

    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/roadmap', [
        'title' => 'Confidential security hardening', 'priority' => 'critical', 'module' => 'security',
    ])->assertStatus(201);
    $internal = RoadmapItem::where('title', 'Confidential security hardening')->first();

    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/roadmap', [
        'title' => 'Faster stock search', 'priority' => 'medium', 'module' => 'inventory', 'status' => 'planned',
        'visibility' => 'customer_visible', 'customer_summary' => 'We are improving stock search results.',
    ])->assertStatus(201);

    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/roadmap/{$internal->id}", ['visibility' => 'customer_visible'])->assertUnprocessable();

    $publicList = $this->actingAs($user)->withHeaders(['X-Tenant-Id' => $tenant->id])->getJson('/api/v1/business/roadmap')->json('data');
    expect(collect($publicList)->pluck('title'))->toContain('Faster stock search')
        ->and(collect($publicList)->pluck('title'))->not->toContain('Confidential security hardening')
        ->and(PlatformAuditLog::where('action', 'platform.improvement.roadmap_item_created')->count())->toBe(2);
});

it('never lets committed code alone close a defect and audits severity changes', function (): void {
    $platform = improvementPlatformUser();
    $defect = DefectRecord::create(['defect_number' => 'DEF-T-001', 'title' => 'Stock totals miscalculated', 'module' => 'inventory', 'environment' => 'staging', 'severity' => 'P1', 'status' => 'open']);

    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/defects/{$defect->id}", ['severity' => 'P3'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/defects/{$defect->id}", ['status' => 'closed'])->assertUnprocessable();

    $path = "/api/v1/platform/improvement/defects/{$defect->id}";
    foreach (['triaged', 'assigned', 'in_progress', 'fixed', 'ready_for_retest'] as $stage) {
        $this->actingAs($platform)->patchJson($path, ['status' => $stage])->assertOk();
    }
    // verify requires a passing retest
    $this->actingAs($platform)->patchJson($path, ['status' => 'verified'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson($path, ['retest_result' => 'pass', 'status' => 'verified'])->assertOk();
    $this->actingAs($platform)->patchJson($path, ['status' => 'closed', 'root_cause' => 'Rounding used float arithmetic', 'related_change_ref' => 'PR #412'])->assertOk();

    $fresh = $defect->fresh();
    expect($fresh->status)->toBe('closed')->and($fresh->retest_verified_by)->toBe($platform->id);

    // reopen requires a documented regression reason
    $this->actingAs($platform)->patchJson($path, ['status' => 'open'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson($path, ['status' => 'open', 'reopen_reason' => 'Regression found in staging retest'])->assertOk();
    expect($defect->fresh()->reopened_count)->toBe(1);
});

it('limits P0 defects from being closed without fix evidence', function (): void {
    $platform = improvementPlatformUser();
    $defect = DefectRecord::create(['defect_number' => 'DEF-T-002', 'title' => 'Tenant data visible across boundary', 'module' => 'platform', 'environment' => 'production', 'severity' => 'P0', 'status' => 'open']);
    $path = "/api/v1/platform/improvement/defects/{$defect->id}";
    foreach (['triaged', 'assigned', 'in_progress', 'fixed', 'ready_for_retest'] as $stage) {
        $this->actingAs($platform)->patchJson($path, ['status' => $stage])->assertOk();
    }
    $this->actingAs($platform)->patchJson($path, ['retest_result' => 'pass', 'status' => 'verified'])->assertOk();
    $this->actingAs($platform)->patchJson($path, ['status' => 'closed', 'root_cause' => 'Missing RLS policy'])->assertUnprocessable(); // no fix evidence
    $this->actingAs($platform)->patchJson($path, ['status' => 'closed', 'root_cause' => 'Missing RLS policy', 'fix_version' => '16.0.3'])->assertOk();
    expect($defect->fresh()->status)->toBe('closed');
});

it('keeps feature flags as rollout metadata only and requires approval for pilot stages', function (): void {
    $platform = improvementPlatformUser();
    [, $tenant] = improvementBusinessUser();
    $flag = FeatureFlag::create(['key' => 'inventory_transfer_beta', 'description' => 'Transfer module beta', 'default_enabled' => false]);

    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/rollouts', [
        'flag_id' => $flag->id, 'scope' => 'tenant', 'stage' => 'development', 'review_at' => now()->addMonths(3)->toDateString(),
    ])->assertStatus(201);
    $rollout = FeatureRollout::first();

    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/rollouts/{$rollout->id}", ['stage' => 'pilot'])->assertUnprocessable();
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/rollouts/{$rollout->id}", ['stage' => 'pilot', 'approval' => true, 'approval_note' => 'Pilot cohort E2E sign-off completed'])->assertOk();

    $this->actingAs($platform)->postJson("/api/v1/platform/improvement/rollouts/{$rollout->id}/tenants", ['tenant_id' => $tenant->id, 'enabled' => true])->assertOk();
    expect(TenantFeatureFlag::where('feature_flag_id', $flag->id)->where('enabled', true)->count())->toBe(1);

    // safe rollback: kill switch disables the flag everywhere; stored business data is untouched
    $this->actingAs($platform)->postJson("/api/v1/platform/improvement/rollouts/{$rollout->id}/kill-switch", ['reason' => 'Queue backlog spike in monitoring'])->assertOk();
    expect($flag->fresh()->default_enabled)->toBeFalse()
        ->and(TenantFeatureFlag::where('feature_flag_id', $flag->id)->first()->enabled)->toBeFalse()
        ->and($rollout->fresh()->stage)->toBe('disabled');
});

it('never marks an unexecuted release deployed and requires real deployment evidence', function (): void {
    $platform = improvementPlatformUser();
    $release = ReleaseRecord::create(['version' => '16.0.0-rc1', 'environment' => 'staging', 'status' => 'draft', 'recorded_by' => $platform->id]);

    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/releases/{$release->id}", ['status' => 'deployed', 'deployment_ref' => 'run-9'])->assertUnprocessable();
    foreach (['ready_for_qa', 'qa_passed', 'approved', 'deploying'] as $stage) {
        $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/releases/{$release->id}", ['status' => $stage])->assertOk();
    }
    $this->actingAs($platform)->patchJson("/api/v1/platform/improvement/releases/{$release->id}", ['status' => 'deployed', 'deployment_ref' => 'github-actions-run-1234'])->assertOk();
    expect($release->fresh()->status)->toBe('deployed')
        ->and($release->fresh()->deployment_ref)->toBe('github-actions-run-1234')
        ->and($release->fresh()->deployed_at)->not->toBeNull();
});

it('computes availability SLO and error budget from real observations; missing data stays unknown', function (): void {
    $platform = improvementPlatformUser();
    $slo = ServiceLevelObjective::create(['name' => 'api availability', 'sli_type' => 'availability', 'metric_source' => 'api', 'target' => '99.000', 'window_days' => 30, 'approved_by' => $platform->id, 'approved_at' => now()]);
    $start = now()->subMinutes(3);
    $uuid = fn () => (string) Illuminate\Support\Str::uuid();
    MonitoringObservation::insert([
        ['id' => $uuid(), 'service' => 'api', 'check_key' => 'availability', 'status' => 'healthy', 'observed_at' => $start],
        ['id' => $uuid(), 'service' => 'api', 'check_key' => 'availability', 'status' => 'down', 'observed_at' => $start->copy()->addMinutes(1)],
        ['id' => $uuid(), 'service' => 'api', 'check_key' => 'availability', 'status' => 'healthy', 'observed_at' => $start->copy()->addMinutes(2)],
    ]);

    $view = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/reliability')->assertOk()->json('data');
    $computed = collect($view['slos'])->first(fn ($s) => $s['id'] === $slo->id);
    expect($computed['observed'])->toEqual(66.667)
        ->and($computed['status'])->toBe('violated')
        ->and($computed['error_budget']['used_seconds'])->toBeGreaterThan(0)
        ->and($computed['error_budget']['used_percent'])->toBeGreaterThan(0);

    $unknown = ServiceLevelObjective::create(['name' => 'queue completion', 'sli_type' => 'queue_completion', 'metric_source' => 'queues', 'target' => '95.000', 'window_days' => 30, 'approved_by' => $platform->id, 'approved_at' => now()]);
    $detail = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/reliability')->json('data');
    $row = collect($detail['slos'])->first(fn ($s) => $s['id'] === $unknown->id);
    expect($row['status'])->toBe('unknown')->and($row['not_configured_reason'])->toBe('no monitoring observations in window');
});

it('reports satisfaction strictly from real responses and demands evidence for maturity', function (): void {
    $platform = improvementPlatformUser();
    [$user, $tenant] = improvementBusinessUser();

    SatisfactionResponse::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'context' => 'release_note', 'score' => 5, 'submitted_at' => now()->subDays(1)]);
    SatisfactionResponse::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'context' => 'release_note', 'context_id' => '3f6d84a3-90d1-4f5b-9c2a-1cd7e0f22aa1', 'score' => 2, 'submitted_at' => now()->subDays(2)]);

    $data = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/satisfaction')->assertOk()->json('data');
    expect($data['summary']['response_count'])->toBe(2)
        ->and($data['summary']['avg_score'])->toEqual(3.5)
        ->and($data['summary']['state'])->toBe('measured')
        ->and($data['nonresponse_count'])->toBe('unknown_denominator_not_measured');

    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/maturity', ['dimension' => 'tenant_isolation', 'period' => '2026-Q4', 'level' => 'managed', 'evidence' => 'short'])->assertUnprocessable();
    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/maturity', [
        'dimension' => 'tenant_isolation', 'period' => '2026-Q4', 'level' => 'managed',
        'evidence' => 'FORCE RLS migration 2026_11_04 plus RlsEnforcementTest regression suite passing.',
    ])->assertStatus(201);
    $this->actingAs($platform)->postJson('/api/v1/platform/improvement/maturity', ['dimension' => 'tenant_isolation', 'period' => '2026-Q4', 'level' => 'optimized', 'evidence' => 'x'])->assertUnprocessable();
    $first = MaturityAssessment::first();
    expect($first->level)->toBe('managed')->and($first->reviewer_id)->toBe($platform->id);
});

it('reports engineering quality honestly with unknown fallbacks and real release-based fail rate', function (): void {
    $platform = improvementPlatformUser();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/quality')->assertOk()->json('data');
    expect($data['change_lead_time']['value'])->toBe('unknown')
        ->and($data['automated_test_failure_trends']['value'])->toBe('unknown')
        ->and($data['deployment_frequency']['available'])->toBeFalse()
        ->and($data['computed_at'])->toBeString();

    ReleaseRecord::create(['version' => '16.0.1', 'environment' => 'production', 'status' => 'deployed', 'deployed_at' => now()->subDay(), 'recorded_by' => $platform->id]);
    ReleaseRecord::create(['version' => '16.0.2', 'environment' => 'production', 'status' => 'rolled_back', 'deployed_at' => now()->addHour(), 'recorded_by' => $platform->id]);
    $after = $this->actingAs($platform)->getJson('/api/v1/platform/improvement/quality')->json('data');
    expect($after['change_fail_rate']['value'])->toEqual(50.0)
        ->and($after['deployment_frequency']['available'])->toBeTrue();
});
