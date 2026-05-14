<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('credentials', 'asset_id') || Schema::hasColumn('credentials', 'category_id')) {
            return;
        }

        Schema::table('credentials', function (Blueprint $table) {
            $table->renameColumn('asset_id', 'category_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('credentials', 'category_id') || Schema::hasColumn('credentials', 'asset_id')) {
            return;
        }

        Schema::table('credentials', function (Blueprint $table) {
            $table->renameColumn('category_id', 'asset_id');
        });
    }
};
