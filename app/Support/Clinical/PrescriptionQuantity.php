<?php

namespace App\Support\Clinical;

use App\Models\PrescriptionItem;

/**
 * Qty resep dari dosis, frekuensi, signa waktu, dan jumlah hari obat.
 * Istilah yang tidak punya angka (PRN, sampai habis tanpa JHO, salep, tetes) tidak dihitung.
 */
final class PrescriptionQuantity
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     mode: string,
     *     quantity: int|null,
     *     unit: string|null,
     *     formula: string,
     *     prn: bool,
     *     until_finished: bool,
     *     liquid: bool,
     *     countable: bool,
     *     dose: float|null,
     *     times_per_day: int|null,
     *     days: int|null,
     *     daily_units: float|null,
     *     administrations: int|null
     * }
     */
    public static function assess(array $input): array
    {
        $text = self::blob($input);
        $prn = self::isPrn($text);
        $untilFinished = self::isUntilFinished($text);
        $days = self::days($input);
        $slots = self::slots($input);
        $schedule = self::schedule($input, $slots);
        $form = self::form($input);

        $base = [
            'mode' => 'incomplete',
            'quantity' => null,
            'unit' => $form['unit'],
            'formula' => '',
            'prn' => $prn,
            'until_finished' => $untilFinished,
            'liquid' => $form['liquid'],
            'countable' => $form['countable'],
            'dose' => $schedule['dose'],
            'times_per_day' => $schedule['times'],
            'days' => $days,
            'daily_units' => $schedule['daily'],
            'administrations' => $schedule['administrations'],
        ];

        if ($prn) {
            $base['mode'] = 'manual';
            $base['formula'] = 'PRN (bila perlu): Qty tidak dihitung otomatis. Isi jumlah yang disetujui dokter.';

            return $base;
        }

        if (! $form['countable'] && ! $form['liquid']) {
            $base['mode'] = 'manual';
            $base['formula'] = 'Sediaan ini tidak memakai rumus tablet. Isi Qty sesuai instruksi dokter.';

            return $base;
        }

        if ($form['liquid'] && ! $form['has_ml']) {
            $base['mode'] = 'manual';
            $base['formula'] = 'Obat cair dihitung dalam ml. Isi dosis ml, atau tentukan Qty manual.';

            return $base;
        }

        if ($untilFinished && $days === null) {
            $base['mode'] = 'manual';
            $base['formula'] = 'Sampai habis: isi jumlah hari obat (JHO) agar Qty terhitung, atau tentukan Qty manual.';

            return $base;
        }

        if ($schedule['daily'] === null || $schedule['daily'] <= 0 || $days === null || $days <= 0) {
            $base['formula'] = 'Qty = dosis × signa × jumlah hari obat.';

            return $base;
        }

        $raw = round($schedule['daily'] * $days, 4);
        $quantity = (int) ceil($raw);
        $unitLabel = $form['liquid'] ? 'ml' : ($form['unit_label'] ?: 'unit');
        $base['mode'] = 'calculated';
        $base['quantity'] = $quantity;
        $base['unit'] = $form['liquid'] ? 'ml' : $base['unit'];
        $base['formula'] = $schedule['explain'].' × '.$days.' hari = '.$quantity.' '.$unitLabel;

        return $base;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    /**
     * Autosave editor: pakai Qty yang dibulatkan ke atas, atau kunci angka yang sudah diisi.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function editorQuantity(array $input): array
    {
        $result = self::assess($input);
        $manual = filter_var($input['quantity_manual'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($result['mode'] === 'calculated' && $result['quantity'] && ! $manual) {
            $adjusted = [
                'quantity' => $result['quantity'],
                'quantity_manual' => false,
            ];
            if ($result['liquid']) {
                $adjusted['unit'] = 'ml';
            }

            return $adjusted;
        }

        if ($result['mode'] === 'manual' && filled($input['quantity'] ?? null)) {
            return ['quantity_manual' => true];
        }

        return [];
    }

    public static function mismatchMessage(array $input): ?string
    {
        $result = self::assess($input);
        $manual = filter_var($input['quantity_manual'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $quantity = filled($input['quantity'] ?? null) ? (int) $input['quantity'] : null;

        if ($result['mode'] === 'calculated' && $quantity !== $result['quantity'] && ! $manual) {
            return $result['formula'].'. Qty tidak sesuai. Ubah Qty lalu simpan lagi jika jumlah ini disengaja.';
        }

        if ($result['prn'] && ! $manual) {
            return $result['formula'];
        }

        if ($result['until_finished'] && $result['days'] === null && ! $manual) {
            return $result['formula'];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $ingredients
     */
    public static function ingredientMessage(array $input, array $ingredients): ?string
    {
        $result = self::assess($input);
        $manual = filter_var($input['quantity_manual'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($result['mode'] !== 'calculated' || $manual || $result['quantity'] === null) {
            return null;
        }

        foreach ($ingredients as $index => $row) {
            if (! is_array($row) || ($row['role'] ?? 'active') === 'additive') {
                continue;
            }
            if (filter_var($row['quantity_manual'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            if (! filled($row['per_dose_quantity'] ?? null)) {
                continue;
            }

            $expected = (int) $row['per_dose_quantity'] * (int) $result['quantity'];
            $given = (int) ($row['quantity'] ?? 0);
            if ($given !== $expected) {
                return 'Bahan ke '.($index + 1).': total '.$expected.' ('.$row['per_dose_quantity'].' × '.$result['quantity'].' bungkus). Konfirmasi jika jumlah bahan diubah manual.';
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function dosageInstruction(PrescriptionItem $item): array
    {
        $result = self::assess([
            'kind' => $item->kind?->value,
            'dosage' => $item->dosage,
            'frequency' => $item->frequency,
            'duration' => $item->duration,
            'course' => $item->course,
            'quantity' => $item->quantity,
            'signa_p' => $item->signa_p,
            'signa_s' => $item->signa_s,
            'signa_sr' => $item->signa_sr,
            'signa_m' => $item->signa_m,
            'waktu' => $item->waktu,
            'jho' => $item->jho,
            'unit' => $item->unit ?: $item->medicine?->unit,
            'notes' => $item->notes,
        ]);

        $instruction = [
            'text' => $item->instructionText() ?: 'Sesuai instruksi dokter',
        ];

        if ($result['prn']) {
            $instruction['asNeededBoolean'] = true;

            return $instruction;
        }

        $times = $result['administrations'];
        if ($result['mode'] === 'calculated' && $times !== null && $times > 0 && $result['days'] !== null && $result['days'] > 0) {
            $instruction['timing'] = [
                'repeat' => [
                    'frequency' => $times,
                    'period' => 1,
                    'periodUnit' => 'd',
                    'boundsDuration' => [
                        'value' => $result['days'],
                        'unit' => 'd',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'd',
                    ],
                ],
            ];
        }

        return $instruction;
    }

    public static function usesMillilitre(PrescriptionItem $item): bool
    {
        return strtolower(trim((string) $item->unit)) === 'ml';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected static function blob(array $input): string
    {
        return strtolower(implode(' ', array_filter([
            (string) ($input['dosage'] ?? ''),
            (string) ($input['frequency'] ?? ''),
            (string) ($input['duration'] ?? ''),
            (string) ($input['course'] ?? ''),
            (string) ($input['waktu'] ?? ''),
            (string) ($input['notes'] ?? ''),
        ], fn ($part) => trim($part) !== '' && trim($part) !== '-')));
    }

    protected static function isPrn(string $text): bool
    {
        return (bool) preg_match('/\bprn\b|\bsos\b|bila perlu|jika diperlukan|pro re nata|si opus sit/u', $text);
    }

    protected static function isUntilFinished(string $text): bool
    {
        return (bool) preg_match('/sampai\s*habis|s\s*\/\s*d\s*habis|\bsd\s*habis/u', $text);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected static function days(array $input): ?int
    {
        if (filled($input['jho'] ?? null) && is_numeric($input['jho'])) {
            $days = (int) $input['jho'];

            return $days > 0 ? $days : null;
        }

        foreach (['duration', 'course', 'notes'] as $key) {
            $text = trim((string) ($input[$key] ?? ''));
            if ($text === '' || $text === '-' || self::isUntilFinished(strtolower($text)) || self::isPrn(strtolower($text))) {
                continue;
            }
            if (preg_match('/^(\d+)\s*hari$/u', $text, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<float|null>|null
     */
    protected static function slots(array $input): ?array
    {
        $keys = ['signa_p', 'signa_s', 'signa_sr', 'signa_m'];
        $filled = false;
        $slots = [];

        foreach ($keys as $key) {
            $raw = trim((string) ($input[$key] ?? ''));
            if ($raw === '') {
                $slots[] = null;

                continue;
            }
            $filled = true;
            $slots[] = self::amount($raw);
        }

        if ($filled) {
            return $slots;
        }

        $frequency = trim((string) ($input['frequency'] ?? ''));
        if (preg_match('/^(\d+(?:[.,]\d+)?)(?:\s*-\s*(\d+(?:[.,]\d+)?)){3}$/', $frequency) === 1) {
            $parts = preg_split('/\s*-\s*/', $frequency) ?: [];

            return array_map(fn ($part) => self::amount((string) $part), $parts);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<float|null>|null  $slots
     * @return array{dose: float|null, times: int|null, daily: float|null, administrations: int|null, explain: string}
     */
    protected static function schedule(array $input, ?array $slots): array
    {
        $doseText = self::amount((string) ($input['dosage'] ?? ''));
        $ml = null;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*ml\b/iu', (string) ($input['dosage'] ?? ''), $match) === 1) {
            $ml = self::amount($match[1]);
        }

        if ($slots !== null) {
            $daily = 0.0;
            $administrations = 0;
            foreach ($slots as $slot) {
                if ($slot !== null && $slot > 0) {
                    $daily += $slot;
                    $administrations++;
                }
            }
            $labels = ['Pagi', 'Siang', 'Sore', 'Malam'];
            $shown = [];
            foreach ($slots as $index => $slot) {
                $shown[] = $labels[$index].' '.($slot === null ? '0' : self::trimNumber($slot));
            }

            $signa = $daily;
            $dose = $ml ?? $doseText;

            if ($dose === null || $signa <= 0) {
                return [
                    'dose' => $dose,
                    'times' => $signa > 0 ? $signa : null,
                    'daily' => null,
                    'administrations' => $administrations > 0 ? $administrations : null,
                    'explain' => 'Qty = dosis × signa × jumlah hari obat.',
                ];
            }

            return [
                'dose' => $dose,
                'times' => $signa,
                'daily' => $dose * $signa,
                'administrations' => $administrations > 0 ? $administrations : null,
                'explain' => self::trimNumber($dose).($ml !== null ? ' ml' : '').' × '.self::trimNumber($signa).' ('.implode(', ', $shown).')',
            ];
        }

        $frequency = (string) ($input['frequency'] ?? '');
        $times = null;
        $pairedDose = null;

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[x×]\s*(\d+(?:[.,]\d+)?)/iu', $frequency, $match) === 1) {
            $times = (int) round((float) str_replace(',', '.', $match[1]));
            $pairedDose = self::amount($match[2]);
        } elseif (preg_match('/(\d+)\s*[x×]\s*sehari/iu', $frequency, $match) === 1) {
            $times = (int) $match[1];
        } elseif (preg_match('/tiap\s+(\d+)\s*jam/iu', $frequency, $match) === 1) {
            $hours = (int) $match[1];
            if ($hours > 0 && 24 % $hours === 0) {
                $times = (int) (24 / $hours);
            }
        }

        $dose = $ml ?? $doseText ?? $pairedDose;

        $daily = ($dose !== null && $times !== null && $times > 0) ? $dose * $times : null;

        return [
            'dose' => $dose,
            'times' => $times,
            'daily' => $daily,
            'administrations' => $times,
            'explain' => self::trimNumber($dose ?? 0).($ml !== null ? ' ml' : '').' × '.($times ?? 0).' kali/hari',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{countable: bool, liquid: bool, has_ml: bool, unit: string|null, unit_label: string}
     */
    protected static function form(array $input): array
    {
        $unit = strtolower(trim((string) ($input['unit'] ?? '')));
        $kind = strtolower(trim((string) ($input['kind'] ?? 'ready')));
        $compound = in_array($kind, ['racikan', 'compound'], true);
        $dosage = strtolower((string) ($input['dosage'] ?? ''));
        $hasMl = preg_match('/\d+(?:[.,]\d+)?\s*ml\b/iu', (string) ($input['dosage'] ?? '')) === 1;
        $plainDose = preg_match('/^\d+(?:[.,]\d+)?$/u', trim((string) ($input['dosage'] ?? ''))) === 1;
        if (! $hasMl && $plainDose && in_array($unit, ['ml', 'sirup'], true)) {
            $hasMl = true;
        }
        $liquidUnit = in_array($unit, ['ml', 'sirup'], true) || str_contains($dosage, 'sirup') || str_contains($dosage, 'sediaan cair');

        if ($hasMl) {
            return ['countable' => false, 'liquid' => true, 'has_ml' => true, 'unit' => 'ml', 'unit_label' => 'ml'];
        }

        if ($compound && (str_contains($dosage, 'salep') || str_contains($dosage, 'tetes') || str_contains($dosage, 'krim'))) {
            return ['countable' => false, 'liquid' => false, 'has_ml' => false, 'unit' => $unit !== '' ? $unit : null, 'unit_label' => ''];
        }

        if ($compound && ! $liquidUnit) {
            return ['countable' => true, 'liquid' => false, 'has_ml' => false, 'unit' => 'bungkus', 'unit_label' => 'bungkus'];
        }

        if ($liquidUnit) {
            return ['countable' => false, 'liquid' => true, 'has_ml' => false, 'unit' => $unit !== '' ? $unit : 'ml', 'unit_label' => 'ml'];
        }

        $manual = ['salep', 'krim', 'tetes', 'tetes_mata', 'tetes_telinga', 'inhaler', 'semprot', 'injeksi', 'infus', 'nebul', 'botol', 'tube', 'ampul'];
        if (in_array($unit, $manual, true)) {
            return ['countable' => false, 'liquid' => false, 'has_ml' => false, 'unit' => $unit, 'unit_label' => $unit];
        }

        $countable = ['tablet', 'tab', 'kapsul', 'kaplet', 'sachet', 'bungkus', 'suppositoria', 'unit', 'pcs', ''];
        $label = match ($unit) {
            'kapsul' => 'kapsul',
            'kaplet' => 'kaplet',
            'sachet' => 'sachet',
            'bungkus' => 'bungkus',
            'suppositoria' => 'suppositoria',
            default => 'tablet',
        };

        return [
            'countable' => in_array($unit, $countable, true),
            'liquid' => false,
            'has_ml' => false,
            'unit' => $unit !== '' ? $unit : null,
            'unit_label' => $label,
        ];
    }

    protected static function amount(string $raw): ?float
    {
        $text = trim(str_replace(',', '.', $raw));
        if ($text === '' || $text === '-') {
            return null;
        }
        if (preg_match('/^(½|1\/2)/u', $text) === 1) {
            return 0.5;
        }
        if (preg_match('/^(¼|1\/4)/u', $text) === 1) {
            return 0.25;
        }
        if (preg_match('/^(¾|3\/4)/u', $text) === 1) {
            return 0.75;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/', $text, $match) === 1) {
            $den = (float) $match[2];

            return $den > 0 ? (float) $match[1] / $den : null;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)/', $text, $match) === 1) {
            return (float) $match[1];
        }

        return null;
    }

    protected static function trimNumber(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }
}
