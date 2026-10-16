<?php

namespace App\Enums;

enum IntegrationLogStatus: string
{
    case Queued = 'queued';
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antrian',
            self::Success => 'Berhasil',
            self::Partial => 'Sebagian',
            self::Failed => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Queued => 'badge bg-info',
            self::Success => 'badge bg-success',
            self::Partial => 'badge bg-warning text-dark',
            self::Failed => 'badge bg-danger',
        };
    }
}
