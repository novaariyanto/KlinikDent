<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjustment => 'Penyesuaian',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::In => 'badge bg-success',
            self::Out => 'badge bg-danger',
            self::Adjustment => 'badge bg-warning',
        };
    }
}
