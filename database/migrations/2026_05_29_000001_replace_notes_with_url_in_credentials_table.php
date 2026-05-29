<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn(['notes_encrypted', 'iv_notes']);
            $table->string('url', 2048)->nullable()->after('iv');
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn('url');
            $table->text('notes_encrypted')->nullable();
            $table->string('iv_notes')->nullable();
        });
    }
};
