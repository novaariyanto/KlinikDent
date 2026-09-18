<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('interval', 20)->default('monthly');
            $table->unsignedInteger('trial_days')->default(14);
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('saas_packages')->restrictOnDelete();
            $table->string('status', 20)->default('trial');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('ends_at');
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('saas_subscriptions')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('issued_at');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('saas_packages')->nullOnDelete();
        });

        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('action', 50);
            $table->string('status', 20)->default('queued');
            $table->nullableMorphs('subject');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('document_path')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->string('satusehat_status', 20)->nullable();
            $table->string('satusehat_id')->nullable();
            $table->timestamp('satusehat_synced_at')->nullable();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->string('bpjs_number', 20)->nullable();
            $table->string('bpjs_status', 20)->nullable();
            $table->timestamp('bpjs_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['bpjs_number', 'bpjs_status', 'bpjs_checked_at']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['satusehat_status', 'satusehat_id', 'satusehat_synced_at']);
        });

        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('integration_logs');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_packages');
    }
};
