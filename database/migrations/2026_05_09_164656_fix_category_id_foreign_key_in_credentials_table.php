<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('credentials', 'category_id')) {
            return;
        }

        $legacyConstraintExists = DB::table('information_schema.table_constraints')
            ->where('table_name', 'credentials')
            ->where('constraint_name', 'credentials_asset_id_foreign')
            ->exists();

        if (! $legacyConstraintExists) {
            return;
        }

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign('credentials_asset_id_foreign');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('credentials', 'category_id')) {
            return;
        }

        $currentConstraintExists = DB::table('information_schema.table_constraints')
            ->where('table_name', 'credentials')
            ->where('constraint_name', 'credentials_category_id_foreign')
            ->exists();

        if (! $currentConstraintExists) {
            return;
        }

        Schema::table('credentials', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('assets')->cascadeOnDelete();
        });
    }
};
