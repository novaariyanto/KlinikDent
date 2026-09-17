<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Waiting = 'waiting';
    case InService = 'in-service';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::InService => 'Dilayani',
            self::Done => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Waiting => 'badge bg-warning',
            self::InService => 'badge bg-info',
            self::Done => 'badge bg-success',
            self::Cancelled => 'badge bg-danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Waiting || $this === self::InService;
    }

    public function workspaceLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::InService => 'Sedang Diperiksa',
            self::Done => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }
}
