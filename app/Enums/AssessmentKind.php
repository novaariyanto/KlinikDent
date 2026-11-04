<?php

namespace App\Enums;

enum AssessmentKind: string
{
    case Initial = 'initial';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Asesmen awal',
            self::FollowUp => 'Asesmen ulang',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Initial => 'badge bg-info',
            self::FollowUp => 'badge bg-secondary',
        };
    }
}
