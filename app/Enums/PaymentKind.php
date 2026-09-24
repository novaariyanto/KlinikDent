<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Payment = 'payment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Pembayaran',
            self::Refund => 'Pengembalian dana',
        };
    }
}
