<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (['accounting_currencies','accounting_exchange_rates','fiscal_years','fiscal_periods','chart_accounts','account_mappings','journal_entries','journal_lines','bank_accounts','bank_reconciliations','tax_configurations'] as $table) { DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY"); DB::statement("CREATE POLICY {$table}_tenant_isolation ON {$table} USING (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true)) WITH CHECK (current_setting('app.platform_admin', true) = 'true' OR tenant_id::text = current_setting('app.tenant_id', true))"); }
        DB::statement("CREATE OR REPLACE FUNCTION prevent_posted_journal_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ DECLARE parent_state text; BEGIN IF TG_TABLE_NAME = 'journal_entries' THEN parent_state := OLD.state; ELSE SELECT state INTO parent_state FROM journal_entries WHERE id = OLD.journal_entry_id; END IF; IF parent_state = 'posted' THEN RAISE EXCEPTION 'Posted journal entries are immutable; create a reversal or adjustment.'; END IF; RETURN NEW; END; $$");
        DB::statement("CREATE TRIGGER journal_entries_immutable BEFORE UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION prevent_posted_journal_mutation()");
        DB::statement("CREATE TRIGGER journal_lines_parent_immutable BEFORE UPDATE OR DELETE ON journal_lines FOR EACH ROW EXECUTE FUNCTION prevent_posted_journal_mutation()");
    }
    public function down(): void { DB::statement('DROP TRIGGER IF EXISTS journal_lines_parent_immutable ON journal_lines'); DB::statement('DROP TRIGGER IF EXISTS journal_entries_immutable ON journal_entries'); DB::statement('DROP FUNCTION IF EXISTS prevent_posted_journal_mutation()'); foreach (['accounting_currencies','accounting_exchange_rates','fiscal_years','fiscal_periods','chart_accounts','account_mappings','journal_entries','journal_lines','bank_accounts','bank_reconciliations','tax_configurations'] as $table) { DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}"); DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY"); } }
};
