<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->json('dental_exam')->nullable()->after('care_notes');
            $table->json('systemic_history')->nullable()->after('dental_exam');
        });

        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->json('surfaces')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['dental_exam', 'systemic_history']);
        });

        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->dropColumn('surfaces');
        });
    }
};
