<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Terkirim',
            self::Fulfilled => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge bg-secondary',
            self::Sent => 'badge bg-info',
            self::Fulfilled => 'badge bg-success',
        };
    }
}
