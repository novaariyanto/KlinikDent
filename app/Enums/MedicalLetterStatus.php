<?php

namespace App\Enums;

enum MedicalLetterStatus: string
{
    case Issued = 'issued';
    case Cancelled = 'cancelled';
    case Revised = 'revised';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Berlaku',
            self::Cancelled => 'Dibatalkan',
            self::Revised => 'Direvisi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Issued => 'badge bg-success',
            self::Cancelled => 'badge bg-danger',
            self::Revised => 'badge bg-info',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
