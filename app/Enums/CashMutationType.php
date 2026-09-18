<?php

namespace App\Enums;

enum CashMutationType: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::In => 'badge bg-success',
            self::Out => 'badge bg-danger',
        };
    }
}
