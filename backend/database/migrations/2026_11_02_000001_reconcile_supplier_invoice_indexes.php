<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('supplier_invoice_submissions')) {
            return;
        }

        // 000040 generated this truncated identifier before the explicit names
        // were added. Rename it when it exists; do not remove the uniqueness
        // guarantee or any business data.
        $legacy = 'supplier_invoice_submissions_tenant_id_company_id_supplier_id_s';
        $scopeUnique = 'supplier_invoice_scope_number_unique';
        $statusIndex = 'supplier_invoice_status_index';

        $legacyExists = DB::selectOne("SELECT to_regclass('public.{$legacy}') AS name")->name ?? null;
        $scopeExists = DB::selectOne("SELECT to_regclass('public.{$scopeUnique}') AS name")->name ?? null;

        if ($legacyExists && ! $scopeExists) {
            DB::statement("ALTER INDEX \"{$legacy}\" RENAME TO \"{$scopeUnique}\"");
        }

        $statusExists = DB::selectOne("SELECT to_regclass('public.{$statusIndex}') AS name")->name ?? null;
        if (! $statusExists) {
            DB::statement("CREATE INDEX \"{$statusIndex}\" ON supplier_invoice_submissions (tenant_id, company_id, supplier_id, status)");
        }
    }

    public function down(): void
    {
        // This corrective migration is intentionally forward-only. Reversing
        // it would reintroduce the PostgreSQL identifier collision.
    }
};
