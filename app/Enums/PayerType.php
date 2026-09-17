<?php

namespace App\Enums;

enum PayerType: string
{
    case Umum = 'umum';
    case Bpjs = 'bpjs';
    case Asuransi = 'asuransi';
    case Corporate = 'corporate';
    case Membership = 'membership';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Umum',
            self::Bpjs => 'BPJS',
            self::Asuransi => 'Asuransi',
            self::Corporate => 'Corporate',
            self::Membership => 'Membership',
        };
    }

    public function routeKey(): string
    {
        return match ($this) {
            self::Umum => 'general',
            self::Bpjs => 'bpjs',
            self::Asuransi => 'insurance',
            self::Corporate => 'corporate',
            self::Membership => 'membership',
        };
    }

    public static function fromRouteKey(string $key): ?self
    {
        return match ($key) {
            'general' => self::Umum,
            'bpjs' => self::Bpjs,
            'insurance' => self::Asuransi,
            'corporate' => self::Corporate,
            'membership' => self::Membership,
            default => self::tryFrom($key),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
