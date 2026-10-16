<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharing_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('calc_type');
            $table->decimal('rate', 15, 4);
            $table->string('material_deduction_type')->default('none');
            $table->decimal('material_deduction_value', 15, 4)->default(0);
            $table->unsignedInteger('priority')->default(100);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'doctor_user_id', 'is_active'], 'sf_rules_doctor_active_idx');
            $table->index(['tenant_id', 'branch_id', 'procedure_id'], 'sf_rules_branch_proc_idx');
            $table->index(['tenant_id', 'priority'], 'sf_rules_priority_idx');
        });

        Schema::create('sharing_fee_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('period_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status');
            $table->decimal('gross_fee_amount', 15, 2)->default(0);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('net_fee_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'sf_settle_status_idx');
            $table->index(['tenant_id', 'doctor_user_id', 'period_start', 'period_end'], 'sf_settle_doctor_period_idx');
            $table->index(['branch_id', 'period_start'], 'sf_settle_branch_period_idx');
        });

        Schema::create('sharing_fee_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('sharing_fee_settlements')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('sharing_fee_rules')->nullOnDelete();
            $table->string('invoice_number');
            $table->string('item_description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('gross_base', 15, 2)->default(0);
            $table->decimal('material_deduction', 15, 2)->default(0);
            $table->decimal('fee_base', 15, 2)->default(0);
            $table->string('calc_type');
            $table->decimal('rate', 15, 4);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->unsignedInteger('rule_priority')->default(0);
            $table->json('rule_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_item_id'], 'sf_items_tenant_invoice_item_uq');
            $table->index(['settlement_id'], 'sf_items_settlement_idx');
            $table->index(['invoice_id'], 'sf_items_invoice_idx');
        });

        Schema::create('sharing_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('sharing_fee_settlements')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method');
            $table->string('reference')->nullable();
            $table->date('paid_on');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_id', 'paid_on']);
        });

        Schema::create('sharing_fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('sharing_fee_settlements')->cascadeOnDelete();
            $table->foreignId('settlement_item_id')->nullable()->constrained('sharing_fee_settlement_items')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharing_fee_adjustments');
        Schema::dropIfExists('sharing_fee_payments');
        Schema::dropIfExists('sharing_fee_settlement_items');
        Schema::dropIfExists('sharing_fee_settlements');
        Schema::dropIfExists('sharing_fee_rules');
    }
};
