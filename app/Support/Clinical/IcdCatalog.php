<?php

namespace App\Support\Clinical;

final class IcdCatalog
{
    /**
     * @return list<array{code: string, description: string}>
     */
    public static function all(): array
    {
        return [
            ['code' => 'K00.0', 'description' => 'Anodoncia'],
            ['code' => 'K00.1', 'description' => 'Gigi supernumerary'],
            ['code' => 'K00.2', 'description' => 'Anomali ukuran dan bentuk gigi'],
            ['code' => 'K00.6', 'description' => 'Gangguan erupsi gigi'],
            ['code' => 'K01.0', 'description' => 'Gigi terpendam'],
            ['code' => 'K01.1', 'description' => 'Gigi impacted'],
            ['code' => 'K02.0', 'description' => 'Karies terbatas pada enamel'],
            ['code' => 'K02.1', 'description' => 'Karies dentin'],
            ['code' => 'K02.2', 'description' => 'Karies semen'],
            ['code' => 'K02.3', 'description' => 'Karies yang terhenti'],
            ['code' => 'K02.5', 'description' => 'Karies dengan pulpitis'],
            ['code' => 'K02.9', 'description' => 'Karies gigi, tidak dijelaskan'],
            ['code' => 'K03.0', 'description' => 'Atrisi gigi berlebih'],
            ['code' => 'K03.1', 'description' => 'Abrasi gigi'],
            ['code' => 'K03.2', 'description' => 'Erosi gigi'],
            ['code' => 'K03.6', 'description' => 'Endapan pada gigi'],
            ['code' => 'K03.8', 'description' => 'Penyakit jaringan keras gigi lainnya'],
            ['code' => 'K04.0', 'description' => 'Pulpitis'],
            ['code' => 'K04.1', 'description' => 'Nekrosis pulpa'],
            ['code' => 'K04.4', 'description' => 'Periodontitis apikal akut asal pulpa'],
            ['code' => 'K04.5', 'description' => 'Periodontitis apikal kronis'],
            ['code' => 'K04.6', 'description' => 'Abses periapikal dengan sinus'],
            ['code' => 'K04.7', 'description' => 'Abses periapikal tanpa sinus'],
            ['code' => 'K05.0', 'description' => 'Gingivitis akut'],
            ['code' => 'K05.1', 'description' => 'Gingivitis kronis'],
            ['code' => 'K05.2', 'description' => 'Periodontitis akut'],
            ['code' => 'K05.3', 'description' => 'Periodontitis kronis'],
            ['code' => 'K05.4', 'description' => 'Periodontosis'],
            ['code' => 'K05.6', 'description' => 'Penyakit periodontal, tidak dijelaskan'],
            ['code' => 'K06.0', 'description' => 'Resesi gingiva'],
            ['code' => 'K07.3', 'description' => 'Anomali posisi gigi'],
            ['code' => 'K07.4', 'description' => 'Maloklusi, tidak dijelaskan'],
            ['code' => 'K08.1', 'description' => 'Kehilangan gigi karena kecelakaan, ekstraksi, atau penyakit periodontal'],
            ['code' => 'K08.3', 'description' => 'Root remnant'],
            ['code' => 'K08.8', 'description' => 'Kelainan gigi dan jaringan pendukung lainnya'],
            ['code' => 'K12.0', 'description' => 'Stomatitis aftosa rekuren'],
            ['code' => 'K12.1', 'description' => 'Bentuk stomatitis lainnya'],
            ['code' => 'K13.0', 'description' => 'Penyakit bibir'],
            ['code' => 'S02.5', 'description' => 'Fraktur gigi'],
            ['code' => 'Z01.2', 'description' => 'Pemeriksaan gigi'],
            ['code' => 'Z46.3', 'description' => 'Pemasangan dan penyesuaian protesa gigi'],
        ];
    }

    /**
     * @return list<array{code: string, description: string}>
     */
    public static function search(string $query, int $limit = 12): array
    {
        $query = trim($query);

        if ($query === '') {
            return array_slice(self::all(), 0, $limit);
        }

        $needle = mb_strtolower($query);

        return collect(self::all())
            ->filter(function (array $item) use ($needle) {
                return str_contains(mb_strtolower($item['code']), $needle)
                    || str_contains(mb_strtolower($item['description']), $needle);
            })
            ->take($limit)
            ->values()
            ->all();
    }
}
