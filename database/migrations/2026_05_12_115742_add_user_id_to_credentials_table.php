<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->after('organization_id');
        });

        // Forma portable: en sqlite Laravel reconstruye la tabla.
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
        });

        // El CHECK es DDL solo de Postgres; en sqlite lo garantiza la capa de
        // aplicacion (IntegrationScopeResolver + CredentialService).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE credentials ADD CONSTRAINT credentials_scope_check
            CHECK (
                (organization_id IS NOT NULL AND user_id IS NULL)
                OR
                (organization_id IS NULL AND user_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE credentials DROP CONSTRAINT IF EXISTS credentials_scope_check');
        }

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });
    }
};
