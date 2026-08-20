<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->string('email')->nullable()->after('username');
            $table->string('nextcloud_account')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn(['email', 'nextcloud_account']);
        });
    }
};
