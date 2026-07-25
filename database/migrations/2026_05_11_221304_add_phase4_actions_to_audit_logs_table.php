<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS_PHASE4 = "
        'login', 'logout', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', 'view',
        'claim', 'claim_failed', 'invite_accepted'
    ";

    private const ACTIONS_PHASE3 = "
        'login', 'logout', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', 'view'
    ";

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // CHECK de acciones: DDL solo de Postgres. En sqlite se omite.
        }

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE4 . '))');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE3 . '))');
    }
};
