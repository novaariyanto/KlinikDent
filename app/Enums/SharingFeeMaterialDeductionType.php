<?php

namespace App\Enums;

enum SharingFeeMaterialDeductionType: string
{
    case None = 'none';
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Percent => 'Persentase',
            self::Fixed => 'Nominal tetap',
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
