<?php

namespace App\Enums;

enum ClinicalDocumentType: string
{
    case SickLetter = 'sick_letter';
    case Referral = 'referral';
    case Resume = 'resume';

    public function label(): string
    {
        return match ($this) {
            self::SickLetter => 'Surat sakit',
            self::Referral => 'Surat rujukan',
            self::Resume => 'Resume medis',
        };
    }
}
