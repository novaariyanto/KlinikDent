<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case NeverMarried = 'S';
    case Married = 'M';
    case Widowed = 'W';
    case Divorced = 'D';

    public function label(): string
    {
        return match ($this) {
            self::NeverMarried => 'Belum kawin',
            self::Married => 'Kawin',
            self::Widowed => 'Cerai mati',
            self::Divorced => 'Cerai hidup',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
