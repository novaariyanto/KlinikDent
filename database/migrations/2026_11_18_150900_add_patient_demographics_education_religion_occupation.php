<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'education')) {
                $table->string('education', 40)->nullable()->after('family_card_number');
            }
            if (! Schema::hasColumn('patients', 'religion')) {
                $table->string('religion', 20)->nullable()->after('education');
            }
            if (! Schema::hasColumn('patients', 'occupation')) {
                $table->string('occupation', 100)->nullable()->after('religion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['education', 'religion', 'occupation'] as $column) {
                if (Schema::hasColumn('patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
