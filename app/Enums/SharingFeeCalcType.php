<?php

namespace App\Enums;

enum SharingFeeCalcType: string
{
    case DoctorFee = 'doctor_fee';
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::DoctorFee => 'Mengikuti jasa dokter',
            self::Percentage => 'Persentase jasa dokter',
            self::Fixed => 'Nominal tetap',
        };
    }

    public function formatRate(string|int|float|null $rate): string
    {
        return match ($this) {
            self::DoctorFee => 'Jasa dokter',
            self::Percentage => number_format((float) $rate, 2, ',', '.').'%',
            self::Fixed => number_format((float) $rate, 0, ',', '.'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
