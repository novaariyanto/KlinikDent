<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_addons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('benefits')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('interval', 20)->default('monthly');
            $table->unsignedInteger('trial_days')->default(0);
            $table->text('terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('saas_addon_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('saas_addons')->restrictOnDelete();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'addon_id']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('saas_addon_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_subscription_id')->constrained('saas_addon_subscriptions')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('unpaid');
            $table->timestamp('issued_at');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_channel', 50)->nullable();
            $table->string('midtrans_order_id')->nullable()->unique();
            $table->string('midtrans_snap_token')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('midtrans_status', 50)->nullable();
            $table->json('payment_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_addon_invoices');
        Schema::dropIfExists('saas_addon_subscriptions');
        Schema::dropIfExists('saas_addons');
    }
};
