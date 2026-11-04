<?php

namespace App\Enums;

enum ToothSurface: string
{
    case Occlusal = 'occlusal';
    case Mesial = 'mesial';
    case Distal = 'distal';
    case Buccal = 'buccal';
    case Palatal = 'palatal';
    case Lingual = 'lingual';
    case Incisal = 'incisal';

    public function label(): string
    {
        return match ($this) {
            self::Occlusal => 'Oklusal',
            self::Mesial => 'Mesial',
            self::Distal => 'Distal',
            self::Buccal => 'Bukal / Labial',
            self::Palatal => 'Palatal',
            self::Lingual => 'Lingual',
            self::Incisal => 'Insisal',
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
            self::Lingual => 'L',
            self::Incisal => 'I',
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
