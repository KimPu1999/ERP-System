<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_platform_audit_mutation() RETURNS trigger AS $$
BEGIN
    IF current_setting('app.allow_audit_prune', true) = 'on' THEN
        RETURN OLD;
    END IF;
    RAISE EXCEPTION 'Platform audit events are immutable';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS platform_audit_immutable_trigger ON platform_audit_logs;
CREATE TRIGGER platform_audit_immutable_trigger
    BEFORE UPDATE OR DELETE ON platform_audit_logs
    FOR EACH ROW EXECUTE FUNCTION prevent_platform_audit_mutation();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;
        DB::unprepared('DROP TRIGGER IF EXISTS platform_audit_immutable_trigger ON platform_audit_logs; DROP FUNCTION IF EXISTS prevent_platform_audit_mutation();');
    }
};
