<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void { if (DB::getDriverName() !== 'pgsql') return; DB::unprepared("CREATE OR REPLACE FUNCTION prevent_stock_movement_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'stock_movements are immutable'; END; $$"); DB::statement('CREATE TRIGGER stock_movements_immutable BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION prevent_stock_movement_mutation()'); }
    public function down(): void { if (DB::getDriverName() !== 'pgsql') return; DB::statement('DROP TRIGGER IF EXISTS stock_movements_immutable ON stock_movements'); DB::statement('DROP FUNCTION IF EXISTS prevent_stock_movement_mutation()'); }
};
