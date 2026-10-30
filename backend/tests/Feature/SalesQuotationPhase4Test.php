<?php

use App\Models\{AccountingCurrency, Company, Permission, Product, ProductVariant, Role, RoleAssignment, Tenant, TenantMembership, UnitOfMeasure, User};

function quotationPhase4Fixture(): array
{
    $user = User::factory()->create(); $tenant = Tenant::factory()->create(); TenantMembership::create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'status'=>'active']);
    $permissions = collect(['sales.manage','sales.approve'])->map(fn($name)=>Permission::firstOrCreate(['name'=>$name])); $role=Role::create(['tenant_id'=>$tenant->id,'name'=>'Quotation operator','slug'=>'quotation-operator']); $role->permissions()->sync($permissions->pluck('id')->all()); RoleAssignment::create(['tenant_id'=>$tenant->id,'role_id'=>$role->id,'user_id'=>$user->id]);
    $company=Company::create(['tenant_id'=>$tenant->id,'name'=>'Quotation Company','code'=>'QT']); $unit=UnitOfMeasure::create(['tenant_id'=>$tenant->id,'name'=>'Each','code'=>'EA']); $product=Product::create(['tenant_id'=>$tenant->id,'name'=>'Quoted product','slug'=>'quoted-product','status'=>'active']); $variant=ProductVariant::create(['tenant_id'=>$tenant->id,'product_id'=>$product->id,'unit_id'=>$unit->id,'sku'=>'QT-1','internal_barcode'=>'QT-1','price'=>'25','cost'=>'10','is_active'=>true]);
    return [$user,$tenant,$company,$variant];
}

it('creates a company-scoped quotation with backend totals', function (): void {
    [$user,$tenant,$company,$variant]=quotationPhase4Fixture(); $customer=\App\Models\Customer::create(['tenant_id'=>$tenant->id,'company_id'=>$company->id,'name'=>'Quotation customer','code'=>'QT-C','customer_type'=>'business']);
    $response=$this->actingAs($user)->postJson('/api/v1/sales/quotations',['company_id'=>$company->id,'customer_id'=>$customer->id,'items'=>[['variant_id'=>$variant->id,'quantity'=>'2','unit_price'=>'25','discount_rate'=>'10','tax_rate'=>'5']]],['X-Tenant-Id'=>$tenant->id])->assertCreated();
    $response->assertJsonPath('data.subtotal','50.000000')->assertJsonPath('data.discount_total','5.000000')->assertJsonPath('data.tax_total','2.250000')->assertJsonPath('data.total','47.250000');
});

it('does not allow quotation conversion before customer acceptance', function (): void {
    [$user,$tenant,$company,$variant]=quotationPhase4Fixture(); $customer=\App\Models\Customer::create(['tenant_id'=>$tenant->id,'company_id'=>$company->id,'name'=>'Quotation customer','code'=>'QT-C2','customer_type'=>'business']); $quotation=$this->actingAs($user)->postJson('/api/v1/sales/quotations',['company_id'=>$company->id,'customer_id'=>$customer->id,'items'=>[['variant_id'=>$variant->id,'quantity'=>'1','unit_price'=>'25']]],['X-Tenant-Id'=>$tenant->id])->json('data');
    $this->actingAs($user)->postJson('/api/v1/sales/quotations/'.$quotation['id'].'/convert',[],['X-Tenant-Id'=>$tenant->id])->assertStatus(422);
});
