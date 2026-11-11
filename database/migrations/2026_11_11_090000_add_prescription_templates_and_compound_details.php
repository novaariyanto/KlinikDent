<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prescription_items', 'course')) {
            Schema::table('prescription_items', function (Blueprint $table) {
                $table->string('course', 100)->nullable()->after('duration');
            });
        }

        if (! Schema::hasColumn('prescription_compound_items', 'per_dose_quantity')) {
            Schema::table('prescription_compound_items', function (Blueprint $table) {
                $table->unsignedInteger('per_dose_quantity')->nullable()->after('quantity');
                $table->string('role', 20)->default('active')->after('unit');
                $table->string('additive_kind', 30)->nullable()->after('role');
            });
        }

        if (! Schema::hasTable('prescription_templates')) {
            $this->createTemplates();
        }

        if (! Schema::hasTable('prescription_template_items')) {
            $this->createTemplateItems();
        }

        if (! Schema::hasTable('prescription_template_ingredients')) {
            $this->createTemplateIngredients();
        } else {
            $this->ensureIngredientForeignKeys();
        }
    }

    protected function createTemplates(): void
    {
        Schema::create('prescription_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }

    protected function createTemplateItems(): void
    {
        Schema::create('prescription_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_template_id')->constrained('prescription_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('kind', 20)->default('ready');
            $table->string('medication_type', 2)->nullable();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->string('course', 100)->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    protected function createTemplateIngredients(): void
    {
        Schema::create('prescription_template_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prescription_template_item_id');
            $table->foreign('prescription_template_item_id', 'rx_tpl_ing_item_fk')
                ->references('id')
                ->on('prescription_template_items')
                ->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('name');
            $table->string('unit', 30)->nullable();
            $table->string('role', 20)->default('active');
            $table->string('additive_kind', 30)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('per_dose_quantity')->nullable();
            $table->decimal('strength_value', 12, 4)->nullable();
            $table->string('strength_unit', 30)->nullable();
            $table->timestamps();
        });
    }

    protected function ensureIngredientForeignKeys(): void
    {
        $names = array_map(
            fn ($row) => $row->CONSTRAINT_NAME,
            DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?',
                ['prescription_template_ingredients', 'FOREIGN KEY']
            )
        );

        if (! in_array('rx_tpl_ing_item_fk', $names, true)) {
            Schema::table('prescription_template_ingredients', function (Blueprint $table) {
                $table->foreign('prescription_template_item_id', 'rx_tpl_ing_item_fk')
                    ->references('id')
                    ->on('prescription_template_items')
                    ->cascadeOnDelete();
            });
        }

        $hasMedicineKey = collect($names)->contains(fn ($name) => str_contains((string) $name, 'medicine'));
        if (! $hasMedicineKey) {
            Schema::table('prescription_template_ingredients', function (Blueprint $table) {
                $table->foreign('medicine_id', 'rx_tpl_ing_medicine_fk')
                    ->references('id')
                    ->on('medicines')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_template_ingredients');
        Schema::dropIfExists('prescription_template_items');
        Schema::dropIfExists('prescription_templates');

        Schema::table('prescription_compound_items', function (Blueprint $table) {
            $table->dropColumn(['per_dose_quantity', 'role', 'additive_kind']);
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('course');
        });
    }
};
