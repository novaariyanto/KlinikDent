<?php

namespace App\Enums;

enum BillingStatus: string
{
    case Unbilled = 'unbilled';
    case Billed = 'billed';

    public function label(): string
    {
        return match ($this) {
            self::Unbilled => 'Belum ditagih',
            self::Billed => 'Sudah ditagih',
        };
    }
}
