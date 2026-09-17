<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Aktif',
            self::Suspended => 'Suspend',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Trial => 'badge bg-warning',
            self::Active => 'badge bg-success',
            self::Suspended => 'badge bg-danger',
        };
    }
}
