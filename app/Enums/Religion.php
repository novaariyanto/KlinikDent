<?php

namespace App\Enums;

enum Religion: string
{
    case Islam = 'islam';
    case Kristen = 'kristen';
    case Katolik = 'katolik';
    case Hindu = 'hindu';
    case Buddha = 'buddha';
    case Konghucu = 'konghucu';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Islam => 'Islam',
            self::Kristen => 'Kristen',
            self::Katolik => 'Katolik',
            self::Hindu => 'Hindu',
            self::Buddha => 'Buddha',
            self::Konghucu => 'Konghucu',
            self::Other => 'Lainnya',
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
