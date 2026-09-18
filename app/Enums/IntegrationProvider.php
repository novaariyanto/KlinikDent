<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case SatuSehat = 'satusehat';
    case Bpjs = 'bpjs';
    case Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::SatuSehat => 'SATUSEHAT',
            self::Bpjs => 'BPJS',
            self::Insurance => 'Asuransi',
        };
    }
}
