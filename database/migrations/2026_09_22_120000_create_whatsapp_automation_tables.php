<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_global_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('category');
            $table->string('label');
            $table->string('default_template_name');
            $table->string('default_language', 10)->default('id');
            $table->text('preview_body');
            $table->json('default_parameter_map');
            $table->boolean('is_recommended')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('api_base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('business_name')->nullable();
            $table->string('default_language', 10)->default('id');
            $table->timestamps();
        });

        Schema::create('whatsapp_tenant_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('global_template_id')->constrained('whatsapp_global_templates')->cascadeOnDelete();
            $table->string('event_key');
            $table->string('whatsapp_template_name');
            $table->string('language_code', 10)->default('id');
            $table->text('preview_body');
            $table->json('parameter_map');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'global_template_id'], 'wa_tenant_templates_unique');
            $table->index(['tenant_id', 'event_key']);
        });

        Schema::create('whatsapp_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_key');
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_key'], 'wa_automation_rules_unique');
        });

        Schema::create('whatsapp_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('whatsapp_template_name')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_enabled', 'starts_at']);
        });

        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('event_key');
            $table->string('category');
            $table->string('template_name');
            $table->string('phone_number', 30)->nullable();
            $table->string('to_name')->nullable();
            $table->json('body_params')->nullable();
            $table->string('status');
            $table->string('provider_message_id')->nullable();
            $table->string('idempotency_key');
            $table->nullableMorphs('related');
            $table->string('scheduled_type', 30)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key'], 'wa_message_logs_idempotent');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'event_key']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('marketing_consent')->default(false)->after('phone');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            $table->index(['tenant_id', 'marketing_consent']);
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->date('control_date')->nullable()->after('plan_instructions');
            $table->date('followup_date')->nullable()->after('control_date');
            $table->index('control_date');
            $table->index('followup_date');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex(['control_date']);
            $table->dropIndex(['followup_date']);
            $table->dropColumn(['control_date', 'followup_date']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'marketing_consent']);
            $table->dropColumn(['marketing_consent', 'marketing_consent_at']);
        });

        Schema::dropIfExists('whatsapp_message_logs');
        Schema::dropIfExists('whatsapp_promotions');
        Schema::dropIfExists('whatsapp_automation_rules');
        Schema::dropIfExists('whatsapp_tenant_templates');
        Schema::dropIfExists('whatsapp_settings');
        Schema::dropIfExists('whatsapp_global_templates');
    }
};
