<?php

namespace App\Enums;

enum BpjsMembershipStatus: string
{
    case Unknown = 'unknown';
    case Active = 'active';
    case Inactive = 'inactive';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Belum dicek',
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Invalid => 'Tidak valid',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Unknown => 'badge bg-secondary',
            self::Active => 'badge bg-success',
            self::Inactive => 'badge bg-warning',
            self::Invalid => 'badge bg-danger',
        };
    }
}
