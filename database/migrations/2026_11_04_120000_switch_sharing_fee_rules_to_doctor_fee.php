<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sharing_fee_rules')
            ->where('calc_type', 'percentage')
            ->update([
                'calc_type' => 'doctor_fee',
                'rate' => 100,
                'material_deduction_type' => 'none',
                'material_deduction_value' => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('sharing_fee_rules')
            ->where('calc_type', 'doctor_fee')
            ->where('rate', 100)
            ->update([
                'calc_type' => 'percentage',
                'rate' => 40,
                'updated_at' => now(),
            ]);
    }
};
