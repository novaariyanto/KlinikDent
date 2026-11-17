<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('prescriptions', 'pharmacy_depot')) {
                $table->string('pharmacy_depot', 100)->nullable()->after('status');
            }
            if (! Schema::hasColumn('prescriptions', 'prescription_kind')) {
                $table->string('prescription_kind', 20)->nullable()->after('pharmacy_depot');
            }
            if (! Schema::hasColumn('prescriptions', 'iter')) {
                $table->unsignedTinyInteger('iter')->nullable()->after('prescription_kind');
            }
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            if (! Schema::hasColumn('prescription_items', 'signa_p')) {
                $table->string('signa_p', 20)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('prescription_items', 'signa_s')) {
                $table->string('signa_s', 20)->nullable()->after('signa_p');
            }
            if (! Schema::hasColumn('prescription_items', 'signa_sr')) {
                $table->string('signa_sr', 20)->nullable()->after('signa_s');
            }
            if (! Schema::hasColumn('prescription_items', 'signa_m')) {
                $table->string('signa_m', 20)->nullable()->after('signa_sr');
            }
            if (! Schema::hasColumn('prescription_items', 'waktu')) {
                $table->string('waktu', 100)->nullable()->after('signa_m');
            }
            if (! Schema::hasColumn('prescription_items', 'jho')) {
                $table->unsignedInteger('jho')->nullable()->after('waktu');
            }
            if (! Schema::hasColumn('prescription_items', 'unit')) {
                $table->string('unit', 30)->nullable()->after('jho');
            }
            if (! Schema::hasColumn('prescription_items', 'notes')) {
                $table->text('notes')->nullable()->after('unit');
            }
            if (! Schema::hasColumn('prescription_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            foreach (['pharmacy_depot', 'prescription_kind', 'iter'] as $column) {
                if (Schema::hasColumn('prescriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            foreach (['signa_p', 'signa_s', 'signa_sr', 'signa_m', 'waktu', 'jho', 'unit', 'notes', 'unit_price'] as $column) {
                if (Schema::hasColumn('prescription_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
