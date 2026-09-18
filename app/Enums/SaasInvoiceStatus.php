<?php

namespace App\Enums;

enum SaasInvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum dibayar',
            self::Paid => 'Lunas',
            self::Void => 'Void',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'badge bg-warning',
            self::Paid => 'badge bg-success',
            self::Void => 'badge bg-secondary',
        };
    }
}
