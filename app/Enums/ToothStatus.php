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
    case CariesProfunda = 'caries_profunda';
    case ResidualRoot = 'residual_root';
    case Attrition = 'attrition';
    case Abrasion = 'abrasion';
    case Erosion = 'erosion';
    case EnamelHypoplasia = 'enamel_hypoplasia';
    case TemporaryFilling = 'temporary_filling';
    case PermanentFilling = 'permanent_filling';
    case Veneer = 'veneer';
    case Inlay = 'inlay';
    case Onlay = 'onlay';
    case PostCore = 'post_core';
    case Pontic = 'pontic';
    case PartialDenture = 'partial_denture';
    case CompleteDenture = 'complete_denture';
    case Agenesis = 'agenesis';
    case Supernumerary = 'supernumerary';
    case PrimaryPersistence = 'primary_persistence';
    case Malposition = 'malposition';
    case Ectopic = 'ectopic';
    case GingivalRecession = 'gingival_recession';
    case Furcation = 'furcation';
    case Calculus = 'calculus';

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
            self::CariesProfunda => 'Karies profunda',
            self::ResidualRoot => 'Sisa akar',
            self::Attrition => 'Atrisi',
            self::Abrasion => 'Abrasi',
            self::Erosion => 'Erosi',
            self::EnamelHypoplasia => 'Hipoplasia enamel',
            self::TemporaryFilling => 'Tumpatan sementara',
            self::PermanentFilling => 'Tumpatan permanen',
            self::Veneer => 'Veneer',
            self::Inlay => 'Inlay',
            self::Onlay => 'Onlay',
            self::PostCore => 'Pasak & inti / Post core',
            self::Pontic => 'Pontik',
            self::PartialDenture => 'Gigi tiruan sebagian (GTS)',
            self::CompleteDenture => 'Gigi tiruan lengkap (GTL)',
            self::Agenesis => 'Agenesis',
            self::Supernumerary => 'Gigi supernumerary',
            self::PrimaryPersistence => 'Persistensi gigi sulung',
            self::Malposition => 'Malposisi',
            self::Ectopic => 'Ektopik',
            self::GingivalRecession => 'Resesi gingiva',
            self::Furcation => 'Furkasi',
            self::Calculus => 'Kalkulus',
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
            self::CariesProfunda => '#B71C1C',
            self::ResidualRoot => '#795548',
            self::Attrition => '#8D6E63',
            self::Abrasion => '#6D4C41',
            self::Erosion => '#FF8F00',
            self::EnamelHypoplasia => '#AFB42B',
            self::TemporaryFilling => '#0288D1',
            self::PermanentFilling => '#1565C0',
            self::Veneer => '#C2185B',
            self::Inlay => '#303F9F',
            self::Onlay => '#5C6BC0',
            self::PostCore => '#8E24AA',
            self::Pontic => '#546E7A',
            self::PartialDenture => '#00897B',
            self::CompleteDenture => '#00838F',
            self::Agenesis => '#757575',
            self::Supernumerary => '#689F38',
            self::PrimaryPersistence => '#E64A19',
            self::Malposition => '#5E35B1',
            self::Ectopic => '#7B1FA2',
            self::GingivalRecession => '#AD1457',
            self::Furcation => '#2E7D32',
            self::Calculus => '#9E9D24',
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
            self::CariesProfunda => 'KP',
            self::ResidualRoot => 'SR',
            self::Attrition => 'AT',
            self::Abrasion => 'AB',
            self::Erosion => 'ER',
            self::EnamelHypoplasia => 'HE',
            self::TemporaryFilling => 'TS',
            self::PermanentFilling => 'TP',
            self::Veneer => 'V',
            self::Inlay => 'IN',
            self::Onlay => 'ON',
            self::PostCore => 'PC',
            self::Pontic => 'P',
            self::PartialDenture => 'GTS',
            self::CompleteDenture => 'GTL',
            self::Agenesis => 'AG',
            self::Supernumerary => 'SN',
            self::PrimaryPersistence => 'PS',
            self::Malposition => 'MP',
            self::Ectopic => 'EK',
            self::GingivalRecession => 'RG',
            self::Furcation => 'FR',
            self::Calculus => 'KL',
            default => '',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Healthy => 'general',
            self::Caries, self::CariesProfunda, self::Fracture, self::ResidualRoot,
            self::Attrition, self::Abrasion, self::Erosion, self::EnamelHypoplasia => 'tissue',
            self::Filling, self::TemporaryFilling, self::PermanentFilling, self::RootCanal,
            self::Crown, self::Veneer, self::Inlay, self::Onlay, self::PostCore, self::Implant,
            self::Bridge, self::Pontic, self::PartialDenture, self::CompleteDenture => 'restoration',
            self::Extracted, self::Missing, self::Impacted, self::Agenesis,
            self::Supernumerary, self::PrimaryPersistence => 'growth',
            self::Mobility, self::Malposition, self::Ectopic, self::GingivalRecession,
            self::Furcation, self::Calculus => 'perio',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'tissue' => 'Jaringan gigi',
            'restoration' => 'Restorasi & rehabilitasi',
            'growth' => 'Kehilangan & pertumbuhan',
            'perio' => 'Posisi & periodontal',
        ];
    }

    /**
     * adult = gigi permanen saja, primary = gigi sulung saja, all = keduanya.
     */
    public function scope(): string
    {
        return match ($this) {
            self::PrimaryPersistence => 'primary',
            self::Veneer, self::Inlay, self::Onlay, self::PostCore,
            self::Pontic, self::PartialDenture, self::CompleteDenture => 'adult',
            default => 'all',
        };
    }

    /**
     * @return list<string>|null
     */
    public function kinds(): ?array
    {
        return match ($this) {
            self::Furcation => ['premolar', 'molar'],
            default => null,
        };
    }

    public function needsSurface(): bool
    {
        return in_array($this, [
            self::Caries,
            self::Filling,
            self::CariesProfunda,
            self::TemporaryFilling,
            self::PermanentFilling,
        ], true);
    }

    public function isWholeTooth(): bool
    {
        return $this !== self::Healthy && ! $this->needsSurface();
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
