<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Aktif',
            self::Expired => 'Habis',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Trial => 'badge bg-warning',
            self::Active => 'badge bg-success',
            self::Expired => 'badge bg-danger',
            self::Cancelled => 'badge bg-secondary',
        };
    }

    public function isLive(): bool
    {
        return $this === self::Trial || $this === self::Active;
    }
}
