<?php

namespace App\Support\Clinical;

use App\Enums\MedicationTypeCode;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Support\Integrations\SatuSehatFhirPayload;

/**
 * Cek kelengkapan item resep agar pengiriman farmasi selaras dengan payload SATUSEHAT
 * (MedicationType NC/EP/SD, KFA, bahan racikan, SIG untuk MedicationRequest).
 */
final class PrescriptionSatuSehatReadiness
{
    /**
     * @return list<array{level: string, message: string, item_id: int|null}>
     */
    public static function forVisit(Visit $visit): array
    {
        $visit->loadMissing(['prescriptions.items.medicine', 'prescriptions.items.ingredients.medicine']);

        $issues = [];
        foreach ($visit->prescriptions as $prescription) {
            foreach (self::forPrescription($prescription) as $issue) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * @return list<array{level: string, message: string, item_id: int|null}>
     */
    public static function forPrescription(Prescription $prescription): array
    {
        $prescription->loadMissing(['items.medicine', 'items.ingredients.medicine']);

        if ($prescription->items->isEmpty()) {
            return [[
                'level' => 'block',
                'message' => 'Resep #'.$prescription->id.' belum punya item obat.',
                'item_id' => null,
            ]];
        }

        $issues = [];
        foreach ($prescription->items as $item) {
            foreach (self::forItem($item) as $issue) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * @return list<array{level: string, message: string, item_id: int|null}>
     */
    public static function forItem(PrescriptionItem $item): array
    {
        $label = $item->label();
        $issues = [];

        if (! self::sigReady($item)) {
            $issues[] = self::issue(
                'warn',
                $item,
                'Lengkapi dosis, signa, dan jumlah hari obat untuk "'.$label.'" sebelum dikirim ke SATUSEHAT.',
            );
        }

        $type = $item->medication_type;
        if (! $type instanceof MedicationTypeCode) {
            $issues[] = self::issue(
                'warn',
                $item,
                'Tipe MedicationType (NC/EP/SD) belum diisi untuk "'.$label.'".',
            );
        } elseif ($item->isCompound() && ! $type->isCompound()) {
            $issues[] = self::issue(
                'warn',
                $item,
                'Racikan "'.$label.'" harus memakai tipe EP atau SD, bukan NC.',
            );
        } elseif (! $item->isCompound() && $type->isCompound()) {
            $issues[] = self::issue(
                'warn',
                $item,
                'Obat jadi "'.$label.'" harus memakai tipe NC.',
            );
        }

        if ($item->isCompound()) {
            $ingredients = $item->relationLoaded('ingredients') ? $item->ingredients : $item->ingredients()->with('medicine')->get();
            if ($ingredients->isEmpty()) {
                $issues[] = self::issue('block', $item, 'Racikan "'.$label.'" belum punya bahan.');
            }

            $isDtd = $type === MedicationTypeCode::GiveSuchDoses;
            foreach ($ingredients as $ingredient) {
                $name = $ingredient->name ?: 'bahan';
                $isAdditive = ($ingredient->role ?? 'active') === 'additive';
                if ($isDtd && ! $isAdditive && ($ingredient->strength_value === null || ! filled($ingredient->strength_unit))) {
                    $issues[] = self::issue(
                        'warn',
                        $item,
                        'Racikan d.t.d (SD) "'.$label.'" membutuhkan kekuatan + unit pada bahan "'.$name.'".',
                    );
                }
            }
        } elseif (! $item->medicine_id || ! $item->medicine) {
            $issues[] = self::issue('block', $item, 'Obat "'.$label.'" tidak terhubung master obat.');
        }

        if ($type instanceof MedicationTypeCode && ! self::lacksKfa($item) && self::canBuildMedication($item) === false) {
            $hasBlock = collect($issues)->contains(fn ($issue) => $issue['level'] === 'block');
            if (! $hasBlock) {
                $issues[] = self::issue(
                    'warn',
                    $item,
                    'Payload Medication SATUSEHAT untuk "'.$label.'" belum dapat dibentuk.',
                );
            }
        }

        return $issues;
    }

    /**
     * @param  list<array{level: string, message: string, item_id: int|null}>  $issues
     * @return list<string>
     */
    public static function blockingMessages(array $issues): array
    {
        return collect($issues)
            ->where('level', 'block')
            ->pluck('message')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{level: string, message: string, item_id: int|null}>  $issues
     * @return list<string>
     */
    public static function warningMessages(array $issues): array
    {
        return collect($issues)
            ->where('level', 'warn')
            ->pluck('message')
            ->unique()
            ->values()
            ->all();
    }

    protected static function lacksKfa(PrescriptionItem $item): bool
    {
        if ($item->isCompound()) {
            $ingredients = $item->relationLoaded('ingredients')
                ? $item->ingredients
                : $item->ingredients()->with('medicine')->get();

            return $ingredients->contains(fn ($ingredient) => filled($ingredient->medicine?->kfa_code)) === false;
        }

        return ! filled($item->medicine?->kfa_code);
    }

    protected static function sigReady(PrescriptionItem $item): bool
    {
        $blob = mb_strtolower(implode(' ', array_filter([
            $item->dosage,
            $item->frequency,
            $item->duration,
            $item->waktu,
            $item->course,
            $item->notes,
        ])));
        $prn = str_contains($blob, 'prn')
            || str_contains($blob, 'sos')
            || str_contains($blob, 'bila perlu')
            || str_contains($blob, 'jika diperlukan');

        $dosageOk = filled($item->dosage) && trim((string) $item->dosage) !== '-';
        $scheduleOk = $item->signaLine() !== ''
            || (filled($item->frequency) && trim((string) $item->frequency) !== '-');
        $daysOk = filled($item->jho)
            || (filled($item->duration) && trim((string) $item->duration) !== '-');

        if ($prn) {
            return $dosageOk;
        }

        return $dosageOk && $scheduleOk && $daysOk;
    }

    public static function canBuildMedication(PrescriptionItem $item): bool
    {
        return SatuSehatFhirPayload::medicationFromItem($item, '0') !== null;
    }

    /**
     * @return array{level: string, message: string, item_id: int|null}
     */
    protected static function issue(string $level, PrescriptionItem $item, string $message): array
    {
        return [
            'level' => $level,
            'message' => $message,
            'item_id' => $item->id,
        ];
    }
}
