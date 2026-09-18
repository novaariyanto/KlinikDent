<?php

namespace App\Enums;

enum InvoiceItemSource: string
{
    case Procedure = 'procedure';
    case Medicine = 'medicine';

    public function label(): string
    {
        return match ($this) {
            self::Procedure => 'Tindakan',
            self::Medicine => 'Obat',
        };
    }
}
