<?php

namespace App\Enums;

enum DoctorFeeWaiverStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak ada',
            self::Pending => 'Menunggu persetujuan',
            self::Applied => 'Diterapkan',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::None => 'bg-secondary',
            self::Pending => 'bg-warning',
            self::Applied => 'bg-success',
            self::Rejected => 'bg-danger',
        };
    }
}
