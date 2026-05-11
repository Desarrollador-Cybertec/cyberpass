<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shared_access_tokens', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('shared_access_tokens', function (Blueprint $table) {
            $table->dropColumn('pin_hash');
        });
    }
};
