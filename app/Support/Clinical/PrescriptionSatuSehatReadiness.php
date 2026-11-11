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

        if (! filled($item->dosage) || ! filled($item->frequency) || ! filled($item->duration)) {
            $issues[] = self::issue(
                'block',
                $item,
                'SIG wajib lengkap (dosis, frekuensi, aturan pakai) untuk "'.$label.'" — dipakai MedicationRequest SATUSEHAT.',
            );
        }

        $type = $item->medication_type;
        if (! $type instanceof MedicationTypeCode) {
            $issues[] = self::issue(
                'block',
                $item,
                'Tipe MedicationType (NC/EP/SD) belum diisi untuk "'.$label.'".',
            );
        } elseif ($item->isCompound() && ! $type->isCompound()) {
            $issues[] = self::issue(
                'block',
                $item,
                'Racikan "'.$label.'" harus memakai tipe EP atau SD, bukan NC.',
            );
        } elseif (! $item->isCompound() && $type->isCompound()) {
            $issues[] = self::issue(
                'block',
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
                        'block',
                        $item,
                        'Racikan d.t.d (SD) "'.$label.'" membutuhkan kekuatan + unit pada bahan "'.$name.'".',
                    );
                }
                if (! $ingredient->medicine_id || ! $ingredient->medicine) {
                    $issues[] = self::issue(
                        'warn',
                        $item,
                        'Bahan "'.$name.'" pada "'.$label.'" belum terhubung master obat/KFA — tidak ke SATUSEHAT.',
                    );

                    continue;
                }
                if (! filled($ingredient->medicine->kfa_code)) {
                    $issues[] = self::issue(
                        'warn',
                        $item,
                        'Bahan "'.$name.'" pada "'.$label.'" belum KFA — tidak dikirim ke SATUSEHAT.',
                    );
                }
            }
        } else {
            if (! $item->medicine_id || ! $item->medicine) {
                $issues[] = self::issue('block', $item, 'Obat "'.$label.'" tidak terhubung master obat.');
            } elseif (! filled($item->medicine->kfa_code)) {
                $issues[] = self::issue(
                    'warn',
                    $item,
                    'Obat "'.$label.'" belum KFA — tidak dikirim ke SATUSEHAT.',
                );
            }
        }

        if ($type instanceof MedicationTypeCode && self::canBuildMedication($item) === false) {
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
