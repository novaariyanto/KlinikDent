<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->string('name')->nullable()->unique()->after('id');
            $table->text('description')->nullable()->after('title');
            $table->string('scope', 20)->default('tenant')->after('description');
            $table->unsignedBigInteger('tenant_id')->nullable()->after('scope');

            $table->index('scope');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['scope']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['name', 'description', 'scope', 'tenant_id']);
        });
    }
};
