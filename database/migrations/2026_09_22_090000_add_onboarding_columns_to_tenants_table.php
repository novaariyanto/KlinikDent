<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('onboarding_status', 20)->default('pending')->after('plan_id');
            $table->string('onboarding_template', 20)->nullable()->after('onboarding_status');
            $table->unsignedTinyInteger('onboarding_current_step')->default(1)->after('onboarding_template');
            $table->json('onboarding_selected_roles')->nullable()->after('onboarding_current_step');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_selected_roles');
        });

        DB::table('tenants')->update([
            'onboarding_status' => 'completed',
            'onboarding_current_step' => 10,
            'onboarding_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_status',
                'onboarding_template',
                'onboarding_current_step',
                'onboarding_selected_roles',
                'onboarding_completed_at',
            ]);
        });
    }
};
