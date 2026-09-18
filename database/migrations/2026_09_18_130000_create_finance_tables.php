<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'type']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->date('expense_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'expense_date']);
        });

        Schema::create('cash_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained('cash_accounts')->restrictOnDelete();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->nullableMorphs('source');
            $table->string('notes')->nullable();
            $table->timestamp('mutated_at');
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['tenant_id', 'mutated_at']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('received_at');
            $table->foreignId('paid_from_account_id')->nullable()->after('paid_at')->constrained('cash_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_from_account_id');
            $table->dropColumn('paid_at');
        });
        Schema::dropIfExists('cash_mutations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('expense_categories');
    }
};
