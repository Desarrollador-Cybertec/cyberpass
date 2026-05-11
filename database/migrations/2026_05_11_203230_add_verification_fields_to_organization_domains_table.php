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
        Schema::table('organization_domains', function (Blueprint $table) {
            $table->string('verification_token')->nullable()->after('is_verified');
            $table->timestamp('verification_expires_at')->nullable()->after('verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('organization_domains', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'verification_expires_at']);
        });
    }
};
