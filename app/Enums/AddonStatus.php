<?php

namespace App\Enums;

enum AddonStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Inactive => 'Nonaktif',
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
            self::Expired => 'Habis',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Inactive => 'badge bg-secondary',
            self::Active => 'badge bg-success',
            self::Suspended => 'badge bg-warning',
            self::Expired => 'badge bg-danger',
        };
    }
}
