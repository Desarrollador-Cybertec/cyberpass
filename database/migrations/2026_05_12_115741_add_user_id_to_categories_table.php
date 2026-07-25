<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()->after('organization_id');
        });

        // Make organization_id nullable (personal categories won't have one).
        // Forma portable: en sqlite Laravel reconstruye la tabla.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->change();
        });

        // Exactly one of organization_id / user_id must be set.
        // El CHECK es DDL solo de Postgres; en sqlite lo garantiza la capa de
        // aplicacion (IntegrationScopeResolver + CredentialService).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE categories ADD CONSTRAINT categories_scope_check
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
            DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_scope_check');
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable(false)->change();
        });
    }
};
