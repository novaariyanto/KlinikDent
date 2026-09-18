<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum dibayar',
            self::Partial => 'Sebagian',
            self::Paid => 'Lunas',
            self::Void => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unpaid => 'badge bg-danger',
            self::Partial => 'badge bg-warning',
            self::Paid => 'badge bg-success',
            self::Void => 'badge bg-secondary',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Unpaid || $this === self::Partial;
    }
}
