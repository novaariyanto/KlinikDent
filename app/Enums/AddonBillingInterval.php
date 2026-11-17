<?php

namespace App\Enums;

enum AddonBillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case OneTime = 'one_time';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
            self::OneTime => 'Sekali bayar',
        };
    }

    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Yearly => 12,
            self::OneTime => null,
        };
    }
}
