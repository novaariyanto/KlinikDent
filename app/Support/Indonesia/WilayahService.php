<?php

namespace App\Support\Indonesia;

use Carbon\Carbon;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

/**
 * Master wilayah dari laravolt/indonesia.
 * Kode tanpa titik: 33 / 3315 / 331513 / 3315131003
 */
class WilayahService
{
    /**
     * @return array<string, string>
     */
    public function provinces(): array
    {
        return Province::query()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->map(fn ($name, $code) => $code.' — '.$name)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function cities(string $provinceCode): array
    {
        $provinceCode = (string) $this->normalizeProvinceCode($provinceCode);

        if ($provinceCode === '') {
            return [];
        }

        return City::query()
            ->where('province_code', $provinceCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (City $city) => [$city->code => $city->code.' — '.$city->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function districts(string $cityCode): array
    {
        $cityCode = (string) $this->normalizeCityCode($cityCode);

        if ($cityCode === '') {
            return [];
        }

        return District::query()
            ->where('city_code', $cityCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (District $district) => [$district->code => $district->code.' — '.$district->name])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function villages(string $districtCode): array
    {
        $districtCode = (string) $this->normalizeDistrictCode($districtCode);

        if ($districtCode === '') {
            return [];
        }

        return Village::query()
            ->where('district_code', $districtCode)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Village $village) => [$village->code => $village->code.' — '.$village->name])
            ->all();
    }

    public function cityName(?string $code): ?string
    {
        $code = $this->normalizeCityCode($code);

        if ($code === null) {
            return null;
        }

        return City::query()->where('code', $code)->value('name');
    }

    public function villageName(?string $code): ?string
    {
        $code = $this->normalizeVillageCode($code);

        if ($code === null) {
            return null;
        }

        return Village::query()->where('code', $code)->value('name');
    }

    public function villagePostalCode(?string $code): ?string
    {
        $code = $this->normalizeVillageCode($code);

        if ($code === null) {
            return null;
        }

        $village = Village::query()->where('code', $code)->first();
        $meta = $village?->meta;

        if (! is_array($meta)) {
            return null;
        }

        $pos = $meta['pos'] ?? null;

        return filled($pos) ? (string) $pos : null;
    }

    public function isProvince(?string $code): bool
    {
        $code = $this->normalizeProvinceCode($code);

        return $code !== null
            && Province::query()->where('code', $code)->exists();
    }

    public function isCity(?string $code, ?string $provinceCode = null): bool
    {
        $code = $this->normalizeCityCode($code);

        if ($code === null) {
            return false;
        }

        $query = City::query()->where('code', $code);

        if ($provinceCode !== null && $provinceCode !== '') {
            $provinceCode = $this->normalizeProvinceCode($provinceCode);
            if ($provinceCode === null) {
                return false;
            }
            $query->where('province_code', $provinceCode);
        }

        return $query->exists();
    }

    public function isDistrict(?string $code, ?string $cityCode = null): bool
    {
        $code = $this->normalizeDistrictCode($code);

        if ($code === null) {
            return false;
        }

        $query = District::query()->where('code', $code);

        if ($cityCode !== null && $cityCode !== '') {
            $cityCode = $this->normalizeCityCode($cityCode);
            if ($cityCode === null) {
                return false;
            }
            $query->where('city_code', $cityCode);
        }

        return $query->exists();
    }

    public function isVillage(?string $code, ?string $districtCode = null): bool
    {
        $code = $this->normalizeVillageCode($code);

        if ($code === null) {
            return false;
        }

        $query = Village::query()->where('code', $code);

        if ($districtCode !== null && $districtCode !== '') {
            $districtCode = $this->normalizeDistrictCode($districtCode);
            if ($districtCode === null) {
                return false;
            }
            $query->where('district_code', $districtCode);
        }

        if ($query->exists()) {
            return true;
        }

        // Master Wilayah SATUSEHAT / Kemendagri: 10 digit, prefix = kode kecamatan.
        $districtCode = $districtCode !== null && $districtCode !== ''
            ? $this->normalizeDistrictCode($districtCode)
            : substr($code, 0, 6);

        return $districtCode !== null
            && strlen($code) === 10
            && str_starts_with($code, $districtCode);
    }

    /**
     * Kode administratif SatuSehat (tanpa titik).
     */
    public function toAdministrativeCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        return $digits !== '' ? $digits : null;
    }

    public function normalizeProvinceCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        return strlen($digits) >= 2 ? substr($digits, 0, 2) : null;
    }

    public function normalizeCityCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        return strlen($digits) >= 4 ? substr($digits, 0, 4) : null;
    }

    public function normalizeDistrictCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        return strlen($digits) >= 6 ? substr($digits, 0, 6) : null;
    }

    public function normalizeVillageCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $code) ?? '';

        return strlen($digits) >= 10 ? substr($digits, 0, 10) : null;
    }

    /**
     * @return array{province?: string, city?: string, district?: string, village?: string}
     */
    public function labels(?string $provinceCode, ?string $cityCode, ?string $districtCode, ?string $villageCode): array
    {
        $provinceCode = $this->normalizeProvinceCode($provinceCode);
        $cityCode = $this->normalizeCityCode($cityCode);
        $districtCode = $this->normalizeDistrictCode($districtCode);
        $villageCode = $this->normalizeVillageCode($villageCode);

        return array_filter([
            'province' => $provinceCode
                ? Province::query()->where('code', $provinceCode)->value('name')
                : null,
            'city' => $cityCode
                ? City::query()->where('code', $cityCode)->value('name')
                : null,
            'district' => $districtCode
                ? District::query()->where('code', $districtCode)->value('name')
                : null,
            'village' => $villageCode
                ? Village::query()->where('code', $villageCode)->value('name')
                : null,
        ]);
    }

    /**
     * Parse NIK → DOB/gender saja (tanpa cek master wilayah).
     *
     * @return array{ok: bool, message?: string, gender?: string|null, dob?: string|null}
     */
    public function identityFromNik(string $nik): array
    {
        $nik = preg_replace('/\D+/', '', $nik) ?? '';

        if (strlen($nik) !== 16) {
            return ['ok' => false, 'message' => 'NIK harus 16 digit.'];
        }

        $dayRaw = (int) substr($nik, 6, 2);
        $month = (int) substr($nik, 8, 2);
        $yearTwo = (int) substr($nik, 10, 2);
        $gender = $dayRaw > 40 ? 'female' : 'male';
        $day = $dayRaw > 40 ? $dayRaw - 40 : $dayRaw;

        return [
            'ok' => true,
            'gender' => $gender,
            'dob' => $this->resolveBirthDate($day, $month, $yearTwo),
        ];
    }

    /**
     * Parse NIK → wilayah laravolt + DOB/gender.
     *
     * NIK valid bila 16 digit. Kode wilayah yang tidak ada di master tidak
     * menolak NIK; provinsi/kab/kota hanya diisi bila kodenya dikenali.
     *
     * @return array{
     *     ok: bool,
     *     message?: string,
     *     province_code?: string,
     *     city_code?: string,
     *     district_code?: string|null,
     *     province_name?: string|null,
     *     city_name?: string|null,
     *     district_name?: string|null,
     *     gender?: string|null,
     *     dob?: string|null,
     *     cities?: array<string, string>,
     *     districts?: array<string, string>,
     *     villages?: array<string, string>
     * }
     */
    public function fromNik(string $nik): array
    {
        $identity = $this->identityFromNik($nik);

        if (! ($identity['ok'] ?? false)) {
            return $identity;
        }

        $nik = preg_replace('/\D+/', '', $nik) ?? '';
        $provinceCode = substr($nik, 0, 2);
        $cityCode = substr($nik, 0, 4);
        $districtCode = substr($nik, 0, 6);
        $provinceKnown = $this->isProvince($provinceCode);
        $cityKnown = $provinceKnown && $this->isCity($cityCode, $provinceCode);

        // Kode wilayah tidak membatalkan NIK. Yang wajib hanya 16 digit.
        if (! $provinceKnown || ! $cityKnown) {
            return [
                'ok' => true,
                'province_code' => null,
                'city_code' => null,
                'district_code' => null,
                'province_name' => null,
                'city_name' => null,
                'district_name' => null,
                'gender' => $identity['gender'] ?? null,
                'dob' => $identity['dob'] ?? null,
                'cities' => [],
                'districts' => [],
                'villages' => [],
            ];
        }

        // Kecamatan dari NIK boleh tidak ada di master (kode lama / pemekaran).
        $resolvedDistrict = $this->isDistrict($districtCode, $cityCode) ? $districtCode : null;
        $labels = $this->labels($provinceCode, $cityCode, $resolvedDistrict, null);

        return [
            'ok' => true,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
            'district_code' => $resolvedDistrict,
            'province_name' => $labels['province'] ?? null,
            'city_name' => $labels['city'] ?? null,
            'district_name' => $labels['district'] ?? null,
            'gender' => $identity['gender'] ?? null,
            'dob' => $identity['dob'] ?? null,
            'cities' => $this->cities($provinceCode),
            'districts' => $this->districts($cityCode),
            'villages' => $resolvedDistrict ? $this->villages($resolvedDistrict) : [],
        ];
    }

    protected function resolveBirthDate(int $day, int $month, int $yearTwo): ?string
    {
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return null;
        }

        $now = Carbon::now();
        $candidates = [];

        foreach ([1900, 2000] as $century) {
            $year = $century + $yearTwo;
            if (! checkdate($month, $day, $year)) {
                continue;
            }
            $date = Carbon::create($year, $month, $day)->startOfDay();
            $age = $date->diffInYears($now);
            if ($age >= 0 && $age <= 120) {
                $candidates[] = $date;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (Carbon $a, Carbon $b) => $a->diffInDays($now) <=> $b->diffInDays($now));

        // Prefer usia wajar 1–100, ambil kandidat terdekat ke "sekarang" dalam rentang itu.
        foreach ($candidates as $date) {
            $age = $date->diffInYears($now);
            if ($age >= 1 && $age <= 100) {
                return $date->format('Y-m-d');
            }
        }

        return $candidates[0]->format('Y-m-d');
    }
}
