<?php

namespace App\Enums;

enum ClinicalDocumentStatus: string
{
    case Valid = 'valid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Berlaku',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
