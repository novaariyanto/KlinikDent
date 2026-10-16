<?php

namespace App\Enums;

enum MedicationTypeCode: string
{
    case NonCompound = 'NC';
    case GiveSuchDoses = 'SD';
    case EqualParts = 'EP';

    public function label(): string
    {
        return match ($this) {
            self::NonCompound => 'Non-racikan (NC)',
            self::GiveSuchDoses => 'Racikan d.t.d (SD)',
            self::EqualParts => 'Racikan bagi sama rata (EP)',
        };
    }

    public function display(): string
    {
        return match ($this) {
            self::NonCompound => 'Non-compound',
            self::GiveSuchDoses => 'Give of such doses',
            self::EqualParts => 'Divide into equal parts',
        };
    }

    public function isCompound(): bool
    {
        return $this !== self::NonCompound;
    }

    /**
     * @return list<self>
     */
    public static function compoundOptions(): array
    {
        return [self::EqualParts, self::GiveSuchDoses];
    }
}
