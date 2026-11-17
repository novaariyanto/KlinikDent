<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'blood_type')) {
                $table->string('blood_type', 3)->nullable()->after('gender');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'blood_type')) {
                $table->dropColumn('blood_type');
            }
        });
    }
};
