<?php

namespace App\Enums;

enum PrescriptionItemKind: string
{
    case Ready = 'ready';
    case Compound = 'racikan';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Obat jadi',
            self::Compound => 'Racikan',
        };
    }
}
