<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('medication_type', 2)->nullable()->after('kind');
        });

        Schema::table('prescription_compound_items', function (Blueprint $table) {
            $table->decimal('strength_value', 12, 4)->nullable()->after('quantity');
            $table->string('strength_unit', 30)->nullable()->after('strength_value');
        });

        // Obat jadi → NC; racikan lama tanpa tipe → EP (bagi sama rata / jumlah bungkus).
        DB::table('prescription_items')
            ->where('kind', 'ready')
            ->whereNull('medication_type')
            ->update(['medication_type' => 'NC']);

        DB::table('prescription_items')
            ->where('kind', 'racikan')
            ->whereNull('medication_type')
            ->update(['medication_type' => 'EP']);
    }

    public function down(): void
    {
        Schema::table('prescription_compound_items', function (Blueprint $table) {
            $table->dropColumn(['strength_value', 'strength_unit']);
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('medication_type');
        });
    }
};
