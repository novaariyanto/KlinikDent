<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->decimal('strength_value', 12, 4)->nullable()->after('kfa_code');
            $table->string('strength_unit', 30)->nullable()->after('strength_value');
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn(['strength_value', 'strength_unit']);
        });
    }
};
