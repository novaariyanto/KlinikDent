<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('satusehat_id')->nullable()->after('nik');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->string('nik', 16)->nullable()->after('user_id');
            $table->string('satusehat_id')->nullable()->after('nik');
            $table->index(['tenant_id', 'nik']);
        });

        Schema::create('branch_satusehat_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20)->default('sandbox');
            $table->string('organization_id')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('location_id')->nullable();
            $table->string('location_name')->nullable();
            $table->boolean('connection_ok')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->boolean('sync_enabled')->default(false);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('branch_id');
        });

        Schema::create('satusehat_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type', 40);
            $table->string('local_key');
            $table->nullableMorphs('internal');
            $table->string('satusehat_id')->nullable();
            $table->string('sync_status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'local_key']);
            $table->index(['branch_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satusehat_resources');
        Schema::dropIfExists('branch_satusehat_settings');

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'nik']);
            $table->dropColumn(['nik', 'satusehat_id']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('satusehat_id');
        });
    }
};
