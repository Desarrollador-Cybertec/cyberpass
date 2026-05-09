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
        Schema::table('credentials', function (Blueprint $table) {
            $table->foreignId('image_id')->nullable()->constrained('images')->nullOnDelete()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_id');
        });
    }
};
