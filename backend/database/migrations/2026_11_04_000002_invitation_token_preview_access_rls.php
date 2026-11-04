<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 14 audit fix (P1): guest invitation preview was broken once row
 * level security is actually enforced (FORCE RLS, previous migration).
 *
 * The unauthenticated `GET /invitations/{token}/preview` flow resolved the
 * invitation solely by token hash. The invitations policy accepted only
 * platform administrators, tenant context sessions or the invitee's own
 * user email, so as a guest the row was never returned. Before FORCE RLS
 * the table-owning application role silently bypassed the policy and the
 * flow appeared to work.
 *
 * Forward-only repair: the connection-local setting `app.invitation_token`
 * (set by the guest controller for the single lookup) now grants read
 * access to the invitation whose token hash matches. Knowledge of the
 * secret token remains the access proof; no other rows become visible.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS invitations_tenant_isolation ON invitations');
        $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true) OR lower(email) = lower(current_setting('app.user_email', true)) OR token_hash = current_setting('app.invitation_token', true))";
        DB::statement("CREATE POLICY invitations_tenant_isolation ON invitations USING {$condition} WITH CHECK {$condition}");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('DROP POLICY IF EXISTS invitations_tenant_isolation ON invitations');
        $condition = "(current_setting('app.platform_admin', true) = 'true' OR tenant_id IS NULL OR tenant_id::text = current_setting('app.tenant_id', true))";
        DB::statement("CREATE POLICY invitations_tenant_isolation ON invitations USING {$condition} WITH CHECK {$condition}");
    }
};
