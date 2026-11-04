<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Completed => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge bg-warning',
            self::Completed => 'badge bg-success',
        };
    }
}
