<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id');

            $table->index('tenant_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['tenant_id', 'branch_id']);
        });
    }
};
