<?php

namespace App\Enums;

enum AssessmentChange: string
{
    case Unchanged = 'unchanged';
    case Improved = 'improved';
    case Worsened = 'worsened';
    case NewFinding = 'new';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Unchanged => 'Tidak berubah',
            self::Improved => 'Membaik',
            self::Worsened => 'Memburuk',
            self::NewFinding => 'Temuan baru',
            self::Resolved => 'Sudah terselesaikan',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
