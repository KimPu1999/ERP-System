<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'supplier.financial.view', 'supplier.financial.manage', 'supplier.bank.view',
            'supplier.bank.submit', 'supplier.bank.verify', 'supplier.bank.default',
            'supplier.payment_hold.view', 'supplier.payment_hold.apply', 'supplier.payment_hold.release',
        ];
        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore(['id' => (string) Str::uuid(), 'name' => $name, 'description' => 'Supplier financial management: '.$name, 'updated_at' => now(), 'created_at' => now()]);
        }
    }
    public function down(): void { DB::table('permissions')->whereIn('name', ['supplier.financial.view','supplier.financial.manage','supplier.bank.view','supplier.bank.submit','supplier.bank.verify','supplier.bank.default','supplier.payment_hold.view','supplier.payment_hold.apply','supplier.payment_hold.release'])->delete(); }
};
