<?php

namespace App\Http\Requests\Concerns;

use App\Support\Indonesia\WilayahService;

trait NormalizesWilayahCodes
{
    /**
     * @param  array<string, mixed>  $merge
     * @return array<string, mixed>
     */
    protected function normalizeWilayahInto(array $merge): array
    {
        $wilayah = app(WilayahService::class);

        if (array_key_exists('province_code', $merge)) {
            $merge['province_code'] = $wilayah->normalizeProvinceCode($merge['province_code'] ?? null);
        }

        if (array_key_exists('city_code', $merge)) {
            $merge['city_code'] = $wilayah->normalizeCityCode($merge['city_code'] ?? null);
        }

        if (array_key_exists('district_code', $merge)) {
            $merge['district_code'] = $wilayah->normalizeDistrictCode($merge['district_code'] ?? null);
        }

        if (array_key_exists('village_code', $merge)) {
            $merge['village_code'] = $wilayah->normalizeVillageCode($merge['village_code'] ?? null);
        }

        return $merge;
    }
}
