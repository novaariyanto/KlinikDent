<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('margin_type')->default('fixed')->after('base_price');
            $table->decimal('margin_value', 15, 2)->default(0)->after('margin_type');
            $table->decimal('sell_price', 15, 2)->default(0)->after('margin_value');
        });

        DB::table('medicines')->update([
            'margin_type' => 'fixed',
            'margin_value' => 0,
            'sell_price' => DB::raw('base_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['margin_type', 'margin_value', 'sell_price']);
        });
    }
};
