<?php

namespace App\Enums;

enum SharingFeeSettlementStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge bg-secondary',
            self::Approved => 'badge bg-info',
            self::Paid => 'badge bg-success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
