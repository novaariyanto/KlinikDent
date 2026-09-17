<?php

namespace App\Enums;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Done = 'done';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::Called => 'Dipanggil',
            self::Done => 'Selesai',
            self::Skipped => 'Dilewati',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Waiting => 'badge bg-warning',
            self::Called => 'badge bg-primary',
            self::Done => 'badge bg-success',
            self::Skipped => 'badge bg-secondary',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Waiting || $this === self::Called;
    }
}
