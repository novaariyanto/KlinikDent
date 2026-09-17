<?php

namespace App\Enums;

enum ToothStatus: string
{
    case Healthy = 'healthy';
    case Caries = 'caries';
    case Filling = 'filling';
    case RootCanal = 'root_canal';
    case Crown = 'crown';
    case Extracted = 'extracted';
    case Missing = 'missing';
    case Fracture = 'fracture';
    case Implant = 'implant';
    case Bridge = 'bridge';
    case Impacted = 'impacted';
    case Mobility = 'mobility';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Sehat',
            self::Caries => 'Karies',
            self::Filling => 'Tumpatan',
            self::RootCanal => 'Perawatan saluran akar',
            self::Crown => 'Mahkota / Crown',
            self::Extracted => 'Dicabut',
            self::Missing => 'Hilang / Extraction',
            self::Fracture => 'Fraktur',
            self::Implant => 'Implan',
            self::Bridge => 'Jembatan / Bridge',
            self::Impacted => 'Impaksi',
            self::Mobility => 'Mobilitas',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => '#e9ecef',
            self::Caries => '#ef4444',
            self::Filling => '#3b82f6',
            self::RootCanal => '#a855f7',
            self::Crown => '#d4a017',
            self::Extracted => '#6b7280',
            self::Missing => '#111827',
            self::Fracture => '#f97316',
            self::Implant => '#14b8a6',
            self::Bridge => '#64748b',
            self::Impacted => '#7c3aed',
            self::Mobility => '#0ea5e9',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Extracted, self::Missing => 'X',
            self::Crown => 'C',
            self::Bridge => 'Br',
            self::Impacted => '▲',
            self::Mobility => 'M',
            self::Implant => 'Im',
            self::RootCanal => 'PSA',
            default => '',
        };
    }

    public function needsSurface(): bool
    {
        return in_array($this, [self::Caries, self::Filling], true);
    }

    public function isWholeTooth(): bool
    {
        return in_array($this, [
            self::Extracted,
            self::Missing,
            self::Crown,
            self::Bridge,
            self::Impacted,
            self::Mobility,
            self::Implant,
            self::RootCanal,
            self::Fracture,
        ], true);
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
