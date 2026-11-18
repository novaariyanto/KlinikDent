<?php

namespace App\Support\Clinical;

final class AllergyHistory
{
    /**
     * @param  array<string, mixed>  $history
     * @return list<array{category: string, type: string, clinical_status: string, severity: string, detail: string, reaction: string, note: string}>
     */
    public static function items(array $history): array
    {
        $raw = $history['allergies'] ?? null;

        if (is_array($raw) && $raw !== []) {
            $items = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = self::normalizeItem($row);
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                return $items;
            }
        }

        $legacy = self::normalizeItem([
            'category' => $history['allergy_category'] ?? '',
            'type' => $history['allergy_type'] ?? '',
            'clinical_status' => $history['allergy_clinical_status'] ?? '',
            'severity' => $history['allergy_severity'] ?? '',
            'detail' => $history['allergy_detail'] ?? '',
            'reaction' => $history['allergy_reaction'] ?? '',
            'note' => $history['allergy_note'] ?? '',
        ]);

        return $legacy ? [$legacy] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{category: string, type: string, clinical_status: string, severity: string, detail: string, reaction: string, note: string}|null
     */
    public static function normalizeItem(array $row): ?array
    {
        $category = trim((string) ($row['category'] ?? ''));
        $detail = trim((string) ($row['detail'] ?? ''));
        $type = trim((string) ($row['type'] ?? ''));
        $clinical = trim((string) ($row['clinical_status'] ?? ''));
        $severity = trim((string) ($row['severity'] ?? ''));
        $reaction = trim((string) ($row['reaction'] ?? ''));
        $note = trim((string) ($row['note'] ?? ''));

        if ($category === '' && $detail === '' && $reaction === '' && $note === '') {
            return null;
        }

        $categories = CareExamOptions::allergyCategories();
        if ($category !== '' && ! array_key_exists($category, $categories)) {
            $category = '';
        }

        return [
            'category' => $category,
            'type' => $type,
            'clinical_status' => $clinical,
            'severity' => $severity,
            'detail' => $detail,
            'reaction' => $reaction,
            'note' => $note,
        ];
    }

    /**
     * @param  array<string, mixed>  $history
     */
    public static function summaryLine(array $history): string
    {
        $status = (string) ($history['allergy_status'] ?? '');
        $buckets = [
            'food' => 'Alergi Makanan',
            'environment' => 'Alergi Udara',
            'medication' => 'Alergi Obat',
            'biologic' => 'Alergi lainnya',
        ];

        return match ($status) {
            'none_confirmed' => 'Tidak ada alergi',
            'unknown' => 'Tidak diketahui',
            'present' => self::presentSummary($history, $buckets),
            default => '—',
        };
    }

    /**
     * @param  array<string, mixed>  $history
     * @param  array<string, string>  $buckets
     */
    protected static function presentSummary(array $history, array $buckets): string
    {
        $items = self::items($history);
        $byCategory = [];

        foreach ($items as $item) {
            $key = $item['category'] !== '' ? $item['category'] : 'biologic';
            $parts = array_filter([
                $item['detail'] !== '' ? $item['detail'] : null,
                $item['reaction'] !== '' ? 'reaksi: '.$item['reaction'] : null,
            ]);
            $byCategory[$key][] = $parts !== [] ? implode(' — ', $parts) : 'Ada';
        }

        if ($byCategory === []) {
            return 'Ada alergi (detail belum diisi)';
        }

        return collect($byCategory)->map(function (array $items, string $key) use ($buckets) {
            $label = $buckets[$key] ?? 'Alergi';

            return $label.': '.implode('; ', $items);
        })->implode(' · ');
    }
}
