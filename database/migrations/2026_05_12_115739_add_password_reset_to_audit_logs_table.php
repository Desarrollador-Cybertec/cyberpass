<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS_PHASE5A = "
        'login', 'logout', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', 'view',
        'claim', 'claim_failed', 'invite_accepted',
        'password_reset'
    ";

    private const ACTIONS_PHASE4 = "
        'login', 'logout', 'reveal_password', 'copy_password',
        'create', 'update', 'delete', 'export', 'assign', 'share',
        '2fa_enabled', '2fa_disabled', '2fa_verified', 'view',
        'claim', 'claim_failed', 'invite_accepted'
    ";

    public function up(): void
    {
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE5A . '))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN (' . self::ACTIONS_PHASE4 . '))');
    }
};
