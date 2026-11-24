<?php

namespace App\Support\Clinical;

/**
 * Pilihan cepat dosis, frekuensi, dan aturan pakai.
 * Teks ini instruksi lokal untuk dokter/farmasi, bukan kode terminologi SATUSEHAT.
 */
class PrescriptionSigCatalog
{
    /**
     * @return list<array{label: string, text: string, hint: string, effect: string, times: int|null, dose: float|null, days: int|null}>
     */
    public static function doses(): array
    {
        return [
            self::option('1 tab', '1', 'Dosis 1. Qty = 1 × signa × jumlah hari obat.', 'dose', dose: 1, units: ['tablet', 'kaplet']),
            self::option('½ tab', '0.5', 'Dosis 0,5. Qty dibulatkan ke atas.', 'dose', dose: 0.5, units: ['tablet', 'kaplet']),
            self::option('2 tab', '2', 'Dosis 2. Qty = 2 × signa × jumlah hari obat.', 'dose', dose: 2, units: ['tablet', 'kaplet']),
            self::option('1 kaps', '1', 'Dosis 1 kapsul. Qty = 1 × signa × jumlah hari obat.', 'dose', dose: 1, units: ['kapsul']),
            self::option('2 kaps', '2', 'Dosis 2 kapsul. Qty = 2 × signa × jumlah hari obat.', 'dose', dose: 2, units: ['kapsul']),
            self::option('5 ml', '5 ml', 'Dosis 5 ml. Qty = 5 ml × signa × jumlah hari obat.', 'dose', dose: 5, units: ['ml', 'sirup']),
            self::option('10 ml', '10 ml', 'Dosis 10 ml. Qty = 10 ml × signa × jumlah hari obat.', 'dose', dose: 10, units: ['ml', 'sirup']),
            self::option('1 cth', '1 cth (sendok teh)', 'Sendok teh. Bukan ml; Qty tidak dihitung sampai dosis ml diisi.', 'text', units: ['ml', 'sirup']),
            self::option('1 csd', '1 csd (sendok makan)', 'Sendok makan. Bukan ml; Qty tidak dihitung sampai dosis ml diisi.', 'text', units: ['ml', 'sirup']),
            self::option('1 bks', '1', 'Dosis 1 bungkus. Qty = 1 × signa × jumlah hari obat.', 'dose', dose: 1, units: ['bungkus', 'sachet']),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string, effect: string, times: int|null, dose: float|null, days: int|null}>
     */
    public static function frequencies(): array
    {
        return [
            self::option('1×1', '1x sehari', 'Satu kali sehari, 1 unit tiap kali.', 'frequency', 1, 1),
            self::option('2×1', '2x sehari', 'Dua kali sehari, 1 unit tiap kali.', 'frequency', 2, 1),
            self::option('3×1', '3x sehari', 'Tiga kali sehari, 1 unit tiap kali. Contoh 5 hari = 15.', 'frequency', 3, 1),
            self::option('3×2', '3x sehari', 'Tiga kali sehari, 2 unit tiap kali. Contoh 5 hari = 30.', 'frequency', 3, 2),
            self::option('4×1', '4x sehari', 'Empat kali sehari, 1 unit tiap kali.', 'frequency', 4, 1),
            self::option('tiap 6 jam', 'tiap 6 jam', 'Setiap 6 jam (4 kali sehari). Dosis diambil dari kolom dosis.', 'frequency', 4),
            self::option('tiap 8 jam', 'tiap 8 jam', 'Setiap 8 jam (3 kali sehari). Dosis diambil dari kolom dosis.', 'frequency', 3),
            self::option('tiap 12 jam', 'tiap 12 jam', 'Setiap 12 jam (2 kali sehari). Dosis diambil dari kolom dosis.', 'frequency', 2),
            self::option('PRN', 'PRN — bila perlu', 'Pro Re Nata: bila perlu. Qty tidak dihitung otomatis.', 'prn'),
            self::option('SOS', 'SOS — bila perlu pada kondisi tertentu', 'Si opus sit. Qty tidak dihitung otomatis.', 'prn'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string, effect: string, times: int|null, dose: float|null, days: int|null}>
     */
    public static function instructions(): array
    {
        return [
            self::option('AC', 'AC — sebelum makan', 'Ante Cibum: sebelum makan. Tidak mengubah Qty.', 'timing'),
            self::option('PC', 'PC — sesudah makan', 'Post Cibum: sesudah makan. Tidak mengubah Qty.', 'timing'),
            self::option('saat makan', 'saat makan', 'Bersamaan dengan makan. Tidak mengubah Qty.', 'timing'),
            self::option('S', 'S — pagi', 'Pagi. Hanya waktu minum, bukan pengali Qty.', 'timing'),
            self::option('M', 'M — siang', 'Siang. Hanya waktu minum, bukan pengali Qty.', 'timing'),
            self::option('N', 'N — malam', 'Malam. Hanya waktu minum, bukan pengali Qty.', 'timing'),
            self::option('HS', 'HS — sebelum tidur', 'Hora Somni: sebelum tidur. Tidak mengubah Qty.', 'timing'),
            self::option('PRN', 'PRN — bila perlu', 'Pro Re Nata: bila perlu. Qty diisi dokter, tidak dihitung.', 'prn'),
            self::option('bila perlu', 'bila perlu', 'Sama dengan PRN. Qty tidak dihitung otomatis.', 'prn'),
            self::option('SOS', 'SOS — bila perlu pada kondisi tertentu', 'Si opus sit. Qty tidak dihitung otomatis.', 'prn'),
            self::option('sampai habis', 'sampai habis', 'Dihitung hanya jika jumlah hari obat diisi. Jika tidak, tentukan Qty.', 'until_finished'),
            self::option('s/d habis', 's/d habis', 'Sampai habis. Bukan PRN. Butuh JHO atau Qty manual.', 'until_finished'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string, effect: string, times: int|null, dose: float|null, days: int|null}>
     */
    public static function courses(): array
    {
        return [
            self::option('3 hari', '3 hari', 'Jumlah hari obat: 3.', 'duration', days: 3),
            self::option('5 hari', '5 hari', 'Jumlah hari obat: 5.', 'duration', days: 5),
            self::option('7 hari', '7 hari', 'Jumlah hari obat: 7.', 'duration', days: 7),
            self::option('10 hari', '10 hari', 'Jumlah hari obat: 10.', 'duration', days: 10),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function compoundForms(): array
    {
        return [
            self::option('Puyer', 'Puyer', 'Bentuk puyer. Satuan bungkus. Qty = 1 × signa × jumlah hari obat.', 'form', units: ['bungkus'], setUnit: 'bungkus'),
            self::option('1 bks', '1', 'Dosis 1 bungkus. Qty = 1 × signa × jumlah hari obat.', 'dose', dose: 1, units: ['bungkus'], setUnit: 'bungkus'),
            self::option('2 bks', '2', 'Dosis 2 bungkus. Qty = 2 × signa × jumlah hari obat.', 'dose', dose: 2, units: ['bungkus'], setUnit: 'bungkus'),
            self::option('Kapsul', 'Kapsul', 'Bentuk kapsul. Qty = 1 × signa × jumlah hari obat.', 'form', units: ['kapsul'], setUnit: 'kapsul'),
            self::option('1 kaps', '1', 'Dosis 1 kapsul. Qty = 1 × signa × jumlah hari obat.', 'dose', dose: 1, units: ['kapsul'], setUnit: 'kapsul'),
            self::option('2 kaps', '2', 'Dosis 2 kapsul. Qty = 2 × signa × jumlah hari obat.', 'dose', dose: 2, units: ['kapsul'], setUnit: 'kapsul'),
            self::option('Sirup', 'Sirup', 'Sediaan sirup. Pilih dosis ml agar Qty terhitung.', 'form', units: ['sirup', 'ml'], setUnit: 'sirup'),
            self::option('Cair', 'Sediaan cair', 'Sediaan cair selain sirup. Pilih dosis ml agar Qty terhitung.', 'form', units: ['sirup', 'ml'], setUnit: 'sirup'),
            self::option('5 ml', '5 ml', 'Dosis 5 ml. Qty = 5 ml × signa × jumlah hari obat.', 'dose', dose: 5, units: ['sirup', 'ml'], setUnit: 'ml'),
            self::option('10 ml', '10 ml', 'Dosis 10 ml. Qty = 10 ml × signa × jumlah hari obat.', 'dose', dose: 10, units: ['sirup', 'ml'], setUnit: 'ml'),
            self::option('1 cth', '1 cth (sendok teh)', 'Sendok teh. Bukan ml; Qty tidak dihitung sampai dosis ml diisi.', 'text', units: ['sirup', 'ml']),
            self::option('1 csd', '1 csd (sendok makan)', 'Sendok makan. Bukan ml; Qty tidak dihitung sampai dosis ml diisi.', 'text', units: ['sirup', 'ml']),
        ];
    }

    /**
     * @return list<array{kind: string, name: string, unit: string, label: string, hint: string}>
     */
    public static function additives(): array
    {
        return [
            [
                'kind' => 'sakarin',
                'name' => 'Sakarin',
                'unit' => 'mg',
                'label' => 'Sakarin',
                'hint' => 'Pemanis opsional untuk racikan anak. Tidak ditambahkan otomatis.',
            ],
            [
                'kind' => 'flavor',
                'name' => 'Bahan perasa',
                'unit' => 'ml',
                'label' => 'Perasa',
                'hint' => 'Flavoring agent. Opsional.',
            ],
            [
                'kind' => 'filler',
                'name' => 'Bahan pengisi',
                'unit' => 'mg',
                'label' => 'Pengisi',
                'hint' => 'Filler. Opsional.',
            ],
            [
                'kind' => 'other',
                'name' => '',
                'unit' => '',
                'label' => 'Lainnya',
                'hint' => 'Bahan tambahan lain. Isi nama secara manual.',
            ],
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function units(): array
    {
        return [
            self::option('mg', 'mg', 'Miligram.'),
            self::option('g', 'g', 'Gram.'),
            self::option('mcg', 'mcg', 'Mikrogram.'),
            self::option('ml', 'ml', 'Mililiter.'),
            self::option('tetes', 'tetes', 'Tetes.'),
            self::option('IU', 'IU', 'International Unit.'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function ingredientAmounts(): array
    {
        return [
            self::option('1', '1', 'Jumlah 1.'),
            self::option('2', '2', 'Jumlah 2.'),
            self::option('5', '5', 'Jumlah 5.'),
            self::option('10', '10', 'Jumlah 10.'),
            self::option('20', '20', 'Jumlah 20.'),
            self::option('50', '50', 'Jumlah 50.'),
            self::option('100', '100', 'Jumlah 100.'),
        ];
    }

    /**
     * @param  list<string>|null  $units
     * @return array{label: string, text: string, hint: string, effect: string, times: int|null, dose: float|null, days: int|null, units: list<string>|null, set_unit: string|null}
     */
    protected static function option(
        string $label,
        string $text,
        string $hint,
        string $effect = 'text',
        ?int $times = null,
        ?float $dose = null,
        ?int $days = null,
        ?array $units = null,
        ?string $setUnit = null,
    ): array {
        return [
            'label' => $label,
            'text' => $text,
            'hint' => $hint,
            'effect' => $effect,
            'times' => $times,
            'dose' => $dose,
            'days' => $days,
            'units' => $units,
            'set_unit' => $setUnit,
        ];
    }
}
