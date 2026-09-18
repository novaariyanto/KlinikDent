<?php

namespace App\Enums;

enum ToothSurface: string
{
    case Occlusal = 'occlusal';
    case Mesial = 'mesial';
    case Distal = 'distal';
    case Buccal = 'buccal';
    case Palatal = 'palatal';

    public function label(): string
    {
        return match ($this) {
            self::Occlusal => 'Oklusal / Insisal',
            self::Mesial => 'Mesial',
            self::Distal => 'Distal',
            self::Buccal => 'Bukal / Labial',
            self::Palatal => 'Palatal / Lingual',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Occlusal => 'O',
            self::Mesial => 'M',
            self::Distal => 'D',
            self::Buccal => 'B',
            self::Palatal => 'P',
        };
    }

    /**
     * @param  array<string, mixed>  $surfaces
     */
    public static function shortList(array $surfaces): string
    {
        return collect($surfaces)
            ->keys()
            ->map(fn ($key) => self::tryFrom((string) $key)?->short() ?? strtoupper(substr((string) $key, 0, 1)))
            ->filter()
            ->implode(', ');
    }
}
