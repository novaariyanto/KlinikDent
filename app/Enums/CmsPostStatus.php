<?php

namespace App\Enums;

enum CmsPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Terjadwal',
            self::Published => 'Published',
            self::Archived => 'Arsip',
            self::Private => 'Privat',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
