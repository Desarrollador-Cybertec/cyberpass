<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS_PHASE8 = "
        'login', 'logout', 'login_failed', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', '2fa_failed', 'view',
        'claim', 'claim_failed', 'invite_accepted',
        'password_reset', 'password_changed',
        'suspend', 'activate',
        'import', 'replicate'
    ";

    private const ACTIONS_PHASE7 = "
        'login', 'logout', 'login_failed', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', '2fa_failed', 'view',
        'claim', 'claim_failed', 'invite_accepted',
        'password_reset', 'password_changed',
        'suspend', 'activate'
    ";

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // CHECK de acciones: DDL solo de Postgres. En sqlite se omite.
        }

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE8 . '))');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE7 . '))');
    }
};
