<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_items', 'name')) {
                $table->string('name', 80)->nullable()->after('medication_type');
            }
            if (! Schema::hasColumn('prescription_items', 'jenis_racikan')) {
                $table->string('jenis_racikan', 60)->nullable()->after('name');
            }
        });

        Schema::table('prescription_compound_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_compound_items', 'dose_amount')) {
                $table->decimal('dose_amount', 12, 4)->nullable()->after('quantity');
            }
        });

        Schema::table('prescription_template_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_template_items', 'name')) {
                $table->string('name', 80)->nullable()->after('medication_type');
            }
            if (! Schema::hasColumn('prescription_template_items', 'jenis_racikan')) {
                $table->string('jenis_racikan', 60)->nullable()->after('name');
            }
        });

        Schema::table('prescription_template_ingredients', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_template_ingredients', 'dose_amount')) {
                $table->decimal('dose_amount', 12, 4)->nullable()->after('quantity');
            }
        });

        Schema::table('medicines', function (Blueprint $table) {
            if (! Schema::hasColumn('medicines', 'generic_name')) {
                $table->string('generic_name', 160)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (Schema::hasColumn('medicines', 'generic_name')) {
                $table->dropColumn('generic_name');
            }
        });

        Schema::table('prescription_template_ingredients', function (Blueprint $table) {
            if (Schema::hasColumn('prescription_template_ingredients', 'dose_amount')) {
                $table->dropColumn('dose_amount');
            }
        });

        foreach (['name', 'jenis_racikan'] as $column) {
            if (Schema::hasColumn('prescription_template_items', $column)) {
                Schema::table('prescription_template_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        Schema::table('prescription_compound_items', function (Blueprint $table) {
            if (Schema::hasColumn('prescription_compound_items', 'dose_amount')) {
                $table->dropColumn('dose_amount');
            }
        });

        foreach (['name', 'jenis_racikan'] as $column) {
            if (Schema::hasColumn('prescription_items', $column)) {
                Schema::table('prescription_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
