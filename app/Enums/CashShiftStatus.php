<?php

namespace App\Enums;

enum CashShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Closed => 'Ditutup',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge bg-success',
            self::Closed => 'badge bg-secondary',
        };
    }
}
