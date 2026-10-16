<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'birth_place')) {
                $table->string('birth_place', 100)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('patients', 'marital_status')) {
                $table->string('marital_status', 10)->nullable()->after('birth_place');
            }
            if (! Schema::hasColumn('patients', 'citizenship')) {
                $table->string('citizenship', 10)->nullable()->default('WNI')->after('marital_status');
            }
            if (! Schema::hasColumn('patients', 'family_card_number')) {
                $table->string('family_card_number', 16)->nullable()->after('citizenship');
            }
            if (! Schema::hasColumn('patients', 'address_city')) {
                $table->string('address_city', 100)->nullable()->after('address');
            }
            if (! Schema::hasColumn('patients', 'address_postal_code')) {
                $table->string('address_postal_code', 10)->nullable()->after('address_city');
            }
            if (! Schema::hasColumn('patients', 'province_code')) {
                $table->string('province_code', 10)->nullable()->after('address_postal_code');
            }
            if (! Schema::hasColumn('patients', 'city_code')) {
                $table->string('city_code', 10)->nullable()->after('province_code');
            }
            if (! Schema::hasColumn('patients', 'district_code')) {
                $table->string('district_code', 10)->nullable()->after('city_code');
            }
            if (! Schema::hasColumn('patients', 'village_code')) {
                $table->string('village_code', 20)->nullable()->after('district_code');
            }
            if (! Schema::hasColumn('patients', 'rt')) {
                $table->string('rt', 5)->nullable()->after('village_code');
            }
            if (! Schema::hasColumn('patients', 'rw')) {
                $table->string('rw', 5)->nullable()->after('rt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach ([
                'birth_place', 'marital_status', 'citizenship', 'family_card_number',
                'address_city', 'address_postal_code', 'province_code', 'city_code',
                'district_code', 'village_code', 'rt', 'rw',
            ] as $column) {
                if (Schema::hasColumn('patients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
