<?php

namespace App\Support\Clinical;

/**
 * Pilihan cepat dosis, frekuensi, dan aturan pakai.
 * Teks ini instruksi lokal untuk dokter/farmasi, bukan kode terminologi SATUSEHAT.
 */
class PrescriptionSigCatalog
{
    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function doses(): array
    {
        return [
            self::option('1 tab', '1 tablet', 'Satu tablet.'),
            self::option('½ tab', '1/2 tablet', 'Setengah tablet.'),
            self::option('2 tab', '2 tablet', 'Dua tablet.'),
            self::option('1 kaps', '1 kapsul', 'Satu kapsul.'),
            self::option('5 ml', '5 ml', 'Lima mililiter.'),
            self::option('10 ml', '10 ml', 'Sepuluh mililiter.'),
            self::option('1 cth', '1 cth (sendok teh)', 'Cth: sendok teh. Singkatan lokal, bukan kode SATUSEHAT.'),
            self::option('1 csd', '1 csd (sendok makan)', 'Csd: sendok makan. Singkatan lokal, bukan kode SATUSEHAT.'),
            self::option('1 bks', '1 bungkus', 'Satu bungkus.'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function frequencies(): array
    {
        return [
            self::option('1×1', '1x sehari', 'Satu kali sehari.'),
            self::option('2×1', '2x sehari', 'Dua kali sehari.'),
            self::option('3×1', '3x sehari', 'Tiga kali sehari.'),
            self::option('4×1', '4x sehari', 'Empat kali sehari.'),
            self::option('tiap 6 jam', 'tiap 6 jam', 'Setiap 6 jam.'),
            self::option('tiap 8 jam', 'tiap 8 jam', 'Setiap 8 jam.'),
            self::option('tiap 12 jam', 'tiap 12 jam', 'Setiap 12 jam.'),
            self::option('PRN', 'PRN — jika diperlukan', 'Pro Re Nata: jika diperlukan. Bukan kode terminologi SATUSEHAT.'),
            self::option('SOS', 'SOS — jika diperlukan dalam kondisi tertentu', 'Si opus sit: jika diperlukan pada kondisi tertentu. Bukan kode SATUSEHAT.'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function instructions(): array
    {
        return [
            self::option('AC', 'AC — sebelum makan', 'Ante Cibum: sebelum makan. Bukan kode SATUSEHAT.'),
            self::option('PC', 'PC — sesudah makan', 'Post Cibum: sesudah makan. Bukan kode SATUSEHAT.'),
            self::option('saat makan', 'saat makan', 'Bersamaan dengan makan.'),
            self::option('HS', 'HS — sebelum tidur', 'Hora Somni: sebelum tidur. Bukan kode SATUSEHAT.'),
            self::option('PRN', 'PRN — jika diperlukan', 'Pro Re Nata: jika diperlukan. Bukan kode SATUSEHAT.'),
            self::option('SOS', 'SOS — jika diperlukan dalam kondisi tertentu', 'Si opus sit: jika diperlukan pada kondisi tertentu. Bukan kode SATUSEHAT.'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function courses(): array
    {
        return [
            self::option('3 hari', '3 hari', 'Durasi 3 hari.'),
            self::option('5 hari', '5 hari', 'Durasi 5 hari.'),
            self::option('7 hari', '7 hari', 'Durasi 7 hari.'),
            self::option('10 hari', '10 hari', 'Durasi 10 hari.'),
            self::option('sampai habis', 'sampai habis', 'Dipakai sampai obat habis.'),
        ];
    }

    /**
     * @return list<array{label: string, text: string, hint: string}>
     */
    public static function compoundForms(): array
    {
        return [
            self::option('Puyer', 'Puyer / serbuk bagi', 'Serbuk yang dibagi ke dalam bungkus.'),
            self::option('Kapsul', 'Kapsul', 'Racikan dalam kapsul.'),
            self::option('Sirup', 'Sirup racikan', 'Sediaan cair sirup racikan.'),
            self::option('Cair', 'Sediaan cair', 'Sediaan cair selain sirup.'),
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
     * @return array{label: string, text: string, hint: string}
     */
    protected static function option(string $label, string $text, string $hint): array
    {
        return [
            'label' => $label,
            'text' => $text,
            'hint' => $hint,
        ];
    }
}
