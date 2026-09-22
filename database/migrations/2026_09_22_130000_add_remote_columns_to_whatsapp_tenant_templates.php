<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_tenant_templates', function (Blueprint $table) {
            $table->string('remote_status', 30)->nullable()->after('is_enabled');
            $table->timestamp('remote_synced_at')->nullable()->after('remote_status');
            $table->json('remote_meta')->nullable()->after('remote_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_tenant_templates', function (Blueprint $table) {
            $table->dropColumn(['remote_status', 'remote_synced_at', 'remote_meta']);
        });
    }
};
