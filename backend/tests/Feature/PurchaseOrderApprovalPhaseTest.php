<?php

use App\Models\{ApprovalDefinition, ApprovalRequest, ApprovalStep, CompanyMembership, ErpNotification, Permission, PurchaseOrder, Role, RoleAssignment, TenantMembership, User};
use Illuminate\Support\Facades\DB;

function purchaseApprovalFixture(): array
{
    [$requester, $tenant, $company, $supplier, $variant] = purchaseOrderWorkspaceFixture();
    CompanyMembership::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'user_id' => $requester->id, 'status' => 'active', 'joined_at' => now()]);

    $permissions = [
        Permission::firstOrCreate(['name' => 'procurement.approve.level1']),
        Permission::firstOrCreate(['name' => 'procurement.approve.level2']),
    ];
    $approvers = [];
    foreach (['Level one approver', 'Level two approver'] as $index => $name) {
        $user = User::factory()->create(['name' => $name]);
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        CompanyMembership::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'user_id' => $user->id, 'status' => 'active', 'joined_at' => now()]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => 'approval-'.fake()->unique()->numerify('####')]);
        $role->permissions()->sync([$permissions[$index]->id]);
        RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id, 'company_id' => $company->id]);
        $approvers[] = $user;
    }

    $definition = ApprovalDefinition::create([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'scenario' => 'purchase_order',
        'name' => 'Purchase order two-level approval',
        'currency_code' => 'MMK',
        'version' => 1,
        'allow_self_approval' => false,
        'is_active' => true,
    ]);
    foreach ([1, 2] as $level) {
        ApprovalStep::create([
            'tenant_id' => $tenant->id,
            'definition_id' => $definition->id,
            'level' => $level,
            'permission' => $level === 1 ? 'procurement.approve.level1' : 'procurement.approve.level2',
            'minimum_amount' => '0',
            'maximum_amount' => null,
            'is_active' => true,
        ]);
    }

    return [$requester, $approvers[0], $approvers[1], $tenant, $company, $supplier, $variant];
}

function approvalHeaders(string $tenantId): array
{
    return ['X-Tenant-Id' => $tenantId];
}

function createApprovalDraft(User $user, string $tenantId, string $companyId, string $supplierId, string $variantId): PurchaseOrder
{
    $response = test()->actingAs($user)->postJson('/api/v1/purchase-orders', [
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'items' => [['variant_id' => $variantId, 'ordered_quantity' => '2', 'unit_price' => '10']],
    ], approvalHeaders($tenantId))->assertCreated();

    return PurchaseOrder::findOrFail($response->json('data.id'));
}

it('runs sequential purchase approval levels and records in-app assignment notifications', function (): void {
    [$requester, $levelOne, $levelTwo, $tenant, $company, $supplier, $variant] = purchaseApprovalFixture();
    $order = createApprovalDraft($requester, $tenant->id, $company->id, $supplier->id, $variant->id);

    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/submit', [], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.state', 'submitted')->assertJsonPath('data.approval_status', 'pending');
    $order->refresh();
    $request = ApprovalRequest::where('document_id', $order->id)->firstOrFail();
    expect($request->approval_cycle)->toBe(1)->and($request->current_level)->toBe(1);
    expect(ErpNotification::where('tenant_id', $tenant->id)->where('user_id', $levelOne->id)->exists())->toBeTrue();
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(0);

    $this->actingAs($levelOne)->postJson('/api/v1/purchase-orders/'.$order->id.'/approve', ['remarks' => 'Level one approved'], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.state', 'submitted')->assertJsonPath('data.approval_status', 'pending');
    expect($request->fresh()->current_level)->toBe(2);

    $this->actingAs($levelTwo)->postJson('/api/v1/purchase-orders/'.$order->id.'/approve', ['remarks' => 'Final approval'], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.state', 'approved')->assertJsonPath('data.approval_status', 'approved');
    expect($request->fresh()->state)->toBe('approved')->and($request->actions()->count())->toBe(2);
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('blocks self approval, missing approval routes, and stale decisions', function (): void {
    [$requester, $levelOne, $levelTwo, $tenant, $company, $supplier, $variant] = purchaseApprovalFixture();
    $order = createApprovalDraft($requester, $tenant->id, $company->id, $supplier->id, $variant->id);

    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/submit', [], approvalHeaders($tenant->id))->assertOk();
    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/approve', [], approvalHeaders($tenant->id))->assertForbidden();
    $this->actingAs($levelOne)->postJson('/api/v1/purchase-orders/'.$order->id.'/approve', [], approvalHeaders($tenant->id))->assertOk();
    $this->actingAs($levelOne)->postJson('/api/v1/purchase-orders/'.$order->id.'/approve', [], approvalHeaders($tenant->id))->assertForbidden();

    $other = createApprovalDraft($requester, $tenant->id, $company->id, $supplier->id, $variant->id);
    ApprovalDefinition::where('id', ApprovalDefinition::where('tenant_id', $tenant->id)->value('id'))->update(['is_active' => false]);
    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$other->id.'/submit', [], approvalHeaders($tenant->id))->assertUnprocessable();
});

it('requires a reason for revision, preserves the prior cycle, and allows resubmission', function (): void {
    [$requester, $levelOne, $levelTwo, $tenant, $company, $supplier, $variant] = purchaseApprovalFixture();
    $order = createApprovalDraft($requester, $tenant->id, $company->id, $supplier->id, $variant->id);
    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/submit', [], approvalHeaders($tenant->id))->assertOk();

    $this->actingAs($levelOne)->postJson('/api/v1/purchase-orders/'.$order->id.'/request-revision', [], approvalHeaders($tenant->id))->assertUnprocessable();
    $this->actingAs($levelOne)->postJson('/api/v1/purchase-orders/'.$order->id.'/request-revision', ['remarks' => 'Please attach the supplier quotation.'], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.state', 'revision_required')->assertJsonPath('data.approval_status', 'revision_required');
    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/revise', [], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.state', 'draft');
    $this->actingAs($requester)->postJson('/api/v1/purchase-orders/'.$order->id.'/submit', [], approvalHeaders($tenant->id))
        ->assertOk()->assertJsonPath('data.approval_cycle', 2);

    expect(ApprovalRequest::where('document_id', $order->id)->count())->toBe(2);
    expect(ApprovalRequest::where('document_id', $order->id)->orderBy('approval_cycle')->first()->actions()->count())->toBe(1);
});
