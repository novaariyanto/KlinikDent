<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalisasi kode wilayah pasien ke digit saja (tanpa titik),
     * setara docs/sql/2026-10-16-normalize-patient-wilayah-dotted.sql.
     */
    public function up(): void
    {
        if (! Schema::hasTable('patients')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasColumn('patients', 'province_code')) {
                DB::statement("
                    UPDATE patients
                    SET province_code = LEFT(REGEXP_REPLACE(province_code, '[^0-9]', ''), 2)
                    WHERE province_code IS NOT NULL AND province_code <> ''
                ");
            }

            if (Schema::hasColumn('patients', 'city_code')) {
                DB::statement("
                    UPDATE patients
                    SET city_code = LEFT(REGEXP_REPLACE(city_code, '[^0-9]', ''), 4)
                    WHERE city_code IS NOT NULL
                      AND city_code <> ''
                      AND CHAR_LENGTH(REGEXP_REPLACE(city_code, '[^0-9]', '')) >= 4
                ");
            }

            if (Schema::hasColumn('patients', 'district_code')) {
                DB::statement("
                    UPDATE patients
                    SET district_code = LEFT(REGEXP_REPLACE(district_code, '[^0-9]', ''), 6)
                    WHERE district_code IS NOT NULL
                      AND district_code <> ''
                      AND CHAR_LENGTH(REGEXP_REPLACE(district_code, '[^0-9]', '')) >= 6
                ");
            }

            if (Schema::hasColumn('patients', 'village_code')) {
                DB::statement("
                    UPDATE patients
                    SET village_code = LEFT(REGEXP_REPLACE(village_code, '[^0-9]', ''), 10)
                    WHERE village_code IS NOT NULL
                      AND village_code <> ''
                      AND CHAR_LENGTH(REGEXP_REPLACE(village_code, '[^0-9]', '')) >= 10
                ");
            }

            return;
        }

        foreach (['province_code' => 2, 'city_code' => 4, 'district_code' => 6, 'village_code' => 10] as $column => $maxLen) {
            if (! Schema::hasColumn('patients', $column)) {
                continue;
            }

            DB::table('patients')
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($column, $maxLen): void {
                    foreach ($rows as $row) {
                        $digits = preg_replace('/\D+/', '', (string) $row->{$column}) ?? '';
                        $minLen = $column === 'province_code' ? 1 : $maxLen;

                        if ($digits === '' || strlen($digits) < $minLen) {
                            continue;
                        }

                        $normalized = substr($digits, 0, $maxLen);

                        if ($normalized !== (string) $row->{$column}) {
                            DB::table('patients')->where('id', $row->id)->update([$column => $normalized]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Data normalisasi tidak dibalikkan (satu arah).
    }
};
