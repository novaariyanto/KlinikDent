<?php

namespace App\Enums;

enum RoomType: string
{
    case Poli = 'poli';
    case Tindakan = 'tindakan';
    case Tunggu = 'tunggu';

    public function label(): string
    {
        return match ($this) {
            self::Poli => 'Poli',
            self::Tindakan => 'Tindakan',
            self::Tunggu => 'Tunggu',
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
