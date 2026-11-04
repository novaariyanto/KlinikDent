<?php

namespace App\Enums;

enum DiagnosisRank: string
{
    case Primary = 'primary';
    case Secondary = 'secondary';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Utama',
            self::Secondary => 'Sekunder',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $rank) => [$rank->value => $rank->label()])->all();
    }
}
