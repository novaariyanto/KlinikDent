<?php

namespace App\Support\Clinical;

final class CareExamOptions
{
    /**
     * @return array<string, string>
     */
    public static function occlusion(): array
    {
        return [
            'normal' => 'Normal Bite',
            'cross' => 'Cross Bite',
            'steep' => 'Steep Bite',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function torus(): array
    {
        return [
            'none' => 'Tidak ada',
            'small' => 'Kecil',
            'medium' => 'Sedang',
            'large' => 'Besar',
            'multiple' => 'Multiple',
        ];
    }

    /**
     * Playbook SATUSEHAT: torus mandibularis memakai sisi, bukan ukuran.
     *
     * @return array<string, string>
     */
    public static function torusMandibularis(): array
    {
        return [
            'none' => 'Tidak ada',
            'left' => 'Sisi Kiri',
            'right' => 'Sisi Kanan',
            'both' => 'Kedua Sisi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function palate(): array
    {
        return [
            'deep' => 'Dalam',
            'medium' => 'Sedang',
            'low' => 'Rendah',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function presence(): array
    {
        return [
            'none' => 'Tidak Ada',
            'present' => 'Ada',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function finding(): array
    {
        return [
            'normal' => 'Normal',
            'abnormal' => 'Ada Kelainan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function gingiva(): array
    {
        return [
            'normal' => 'Normal',
            'inflamed' => 'Radang',
            'recession' => 'Resesi',
            'bleeding' => 'Mudah berdarah',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mucosa(): array
    {
        return [
            'normal' => 'Normal',
            'lesion' => 'Lesion / kelainan',
            'ulcer' => 'Ulkus',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function systemic(): array
    {
        return [
            'hypertension' => 'Hipertensi',
            'diabetes' => 'Diabetes Melitus',
            'heart_disease' => 'Penyakit Jantung',
            'asthma' => 'Asma',
            'allergy' => 'Alergi',
            'infectious' => 'Penyakit Menular',
            'medication' => 'Obat rutin',
            'hepatitis' => 'Hepatitis',
            'hiv' => 'HIV',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allergyStatuses(): array
    {
        return [
            'not_asked' => 'Belum ditanyakan',
            'unknown' => 'Tidak diketahui',
            'none_confirmed' => 'Tidak ada alergi yang diketahui',
            'present' => 'Ada alergi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allergyCategories(): array
    {
        return [
            'medication' => 'Obat',
            'food' => 'Makanan',
            'environment' => 'Lingkungan',
            'biologic' => 'Biologis',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allergyTypes(): array
    {
        return [
            'allergy' => 'Alergi',
            'intolerance' => 'Intoleransi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allergyClinicalStatuses(): array
    {
        return [
            'active' => 'Masih berlangsung',
            'inactive' => 'Tidak aktif',
            'resolved' => 'Sudah sembuh',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allergySeverities(): array
    {
        return [
            'mild' => 'Ringan',
            'moderate' => 'Sedang',
            'severe' => 'Berat',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function carePlanStatuses(): array
    {
        return [
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'on-hold' => 'Ditunda',
        ];
    }

    public static function instructions(): array
    {
        return [
            'kontrol_1_minggu' => 'Kontrol 1 minggu',
            'kontrol_2_minggu' => 'Kontrol 2 minggu',
            'jaga_kebersihan' => 'Jaga kebersihan gigi',
            'jangan_kunyah' => 'Jangan mengunyah pada area tindakan',
            'minum_obat' => 'Minum obat sesuai aturan',
            'kembali_jika_bertambah' => 'Kembali jika keluhan bertambah',
            'lainnya' => 'Instruksi lainnya',
        ];
    }
}
