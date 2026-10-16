<?php

namespace App\Enums;

enum Citizenship: string
{
    case Wni = 'WNI';
    case Wna = 'WNA';

    public function label(): string
    {
        return match ($this) {
            self::Wni => 'WNI',
            self::Wna => 'WNA',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $item) => [$item->value => $item->label()])
            ->all();
    }
}
