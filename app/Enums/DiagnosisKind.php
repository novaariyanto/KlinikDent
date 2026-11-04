<?php

namespace App\Enums;

enum DiagnosisKind: string
{
    case Working = 'working';
    case Clinical = 'clinical';
    case Differential = 'differential';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Diagnosis kerja',
            self::Clinical => 'Diagnosis klinis',
            self::Differential => 'Diagnosis banding',
            self::Final => 'Diagnosis akhir',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $kind) => [$kind->value => $kind->label()])->all();
    }
}
