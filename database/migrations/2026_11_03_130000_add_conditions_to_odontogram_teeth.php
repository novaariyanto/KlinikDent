<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('surfaces');
        });
    }

    public function down(): void
    {
        Schema::table('odontogram_teeth', function (Blueprint $table) {
            $table->dropColumn('conditions');
        });
    }
};
