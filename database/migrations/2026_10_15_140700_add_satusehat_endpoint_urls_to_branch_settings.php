<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_satusehat_settings', function (Blueprint $table) {
            $table->string('base_url')->nullable()->after('environment');
            $table->string('token_url')->nullable()->after('base_url');
        });
    }

    public function down(): void
    {
        Schema::table('branch_satusehat_settings', function (Blueprint $table) {
            $table->dropColumn(['base_url', 'token_url']);
        });
    }
};
