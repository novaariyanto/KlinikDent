<?php

namespace App\Enums;

enum WhatsAppCategory: string
{
    case Appointment = 'appointment';
    case Treatment = 'treatment';
    case Billing = 'billing';
    case Pharmacy = 'pharmacy';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Appointment => 'APPOINTMENT',
            self::Treatment => 'PERAWATAN',
            self::Billing => 'BILLING',
            self::Pharmacy => 'FARMASI',
            self::Marketing => 'MARKETING',
        };
    }

    public function whatsCrmCategory(): string
    {
        return $this === self::Marketing ? 'marketing' : 'utility';
    }
}
