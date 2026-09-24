<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case ReturnOut = 'return_out';
    case ReturnIn = 'return_in';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjustment => 'Penyesuaian',
            self::ReturnOut => 'Retur supplier',
            self::ReturnIn => 'Retur pasien',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::In => 'badge bg-success',
            self::Out => 'badge bg-danger',
            self::Adjustment => 'badge bg-warning',
            self::ReturnOut => 'badge bg-danger',
            self::ReturnIn => 'badge bg-success',
        };
    }
}
