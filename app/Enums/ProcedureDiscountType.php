<?php

namespace App\Enums;

enum ProcedureDiscountType: string
{
    case Nominal = 'nominal';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Nominal => 'Nominal',
            self::Percent => 'Persentase',
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
