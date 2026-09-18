<?php

namespace App\Enums;

enum SatuSehatStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Synced = 'synced';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum dikirim',
            self::Queued => 'Dalam antrian',
            self::Synced => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge bg-secondary',
            self::Queued => 'badge bg-info',
            self::Synced => 'badge bg-success',
            self::Failed => 'badge bg-danger',
        };
    }
}
