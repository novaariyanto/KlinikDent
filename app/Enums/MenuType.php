<?php

namespace App\Enums;

enum MenuType: string
{
    case Item = 'item';
    case Heading = 'heading';

    public function label(): string
    {
        return match ($this) {
            self::Item => 'Item',
            self::Heading => 'Heading',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Item => 'badge badge-soft-primary',
            self::Heading => 'badge badge-soft-warning',
        };
    }
}
