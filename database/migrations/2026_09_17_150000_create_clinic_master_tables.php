<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('contract_number')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->string('category');
            $table->decimal('base_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('procedure_id')->constrained('procedures')->cascadeOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->nullOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('effective_date');
            $table->timestamps();

            $table->index(['tenant_id', 'procedure_id', 'effective_date']);
            $table->index(['tenant_id', 'branch_id', 'payer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariffs');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('procedures');
        Schema::dropIfExists('services');
        Schema::dropIfExists('payers');
    }
};
