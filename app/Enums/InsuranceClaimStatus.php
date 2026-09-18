<?php

namespace App\Enums;

enum InsuranceClaimStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Paid => 'Dibayar',
            self::Rejected => 'Ditolak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge bg-secondary',
            self::Submitted => 'badge bg-info',
            self::Paid => 'badge bg-success',
            self::Rejected => 'badge bg-danger',
        };
    }
}
