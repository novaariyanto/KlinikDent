<?php

namespace App\Support\Onboarding;

/**
 * Master tarif klinik gigi dari public/master_tarif_klinik_gigi_klinikdent.xlsx
 */
final class DentalTariffCatalog
{
    /**
     * @return list<array{
     *     code: string,
     *     procedure: string,
     *     service: string,
     *     category: string,
     *     price: int,
     *     bhp_amount: int,
     *     doctor_fee: int,
     *     clinic_fee: int
     * }>
     */
    public static function items(): array
    {
        return [
            ['code' => 'ADM001', 'procedure' => 'Pemeriksaan gigi umum', 'service' => 'Pemeriksaan', 'category' => 'pemeriksaan', 'price' => 20000, 'bhp_amount' => 5000, 'doctor_fee' => 8000, 'clinic_fee' => 7000],
            ['code' => 'ADM002', 'procedure' => 'Konsultasi dokter gigi', 'service' => 'Pemeriksaan', 'category' => 'pemeriksaan', 'price' => 30000, 'bhp_amount' => 3000, 'doctor_fee' => 18000, 'clinic_fee' => 9000],
            ['code' => 'ADM003', 'procedure' => 'Kontrol pasca perawatan', 'service' => 'Pemeriksaan', 'category' => 'pemeriksaan', 'price' => 25000, 'bhp_amount' => 4000, 'doctor_fee' => 14000, 'clinic_fee' => 7000],
            ['code' => 'PRE001', 'procedure' => 'Scaling seluruh rahang', 'service' => 'Preventif', 'category' => 'preventif', 'price' => 200000, 'bhp_amount' => 35000, 'doctor_fee' => 95000, 'clinic_fee' => 70000],
            ['code' => 'PRE002', 'procedure' => 'Scaling per rahang', 'service' => 'Preventif', 'category' => 'preventif', 'price' => 100000, 'bhp_amount' => 18000, 'doctor_fee' => 47000, 'clinic_fee' => 35000],
            ['code' => 'PRE003', 'procedure' => 'Polishing gigi', 'service' => 'Preventif', 'category' => 'preventif', 'price' => 50000, 'bhp_amount' => 10000, 'doctor_fee' => 15000, 'clinic_fee' => 25000],
            ['code' => 'PRE004', 'procedure' => 'Aplikasi fluoride', 'service' => 'Preventif', 'category' => 'preventif', 'price' => 100000, 'bhp_amount' => 30000, 'doctor_fee' => 30000, 'clinic_fee' => 40000],
            ['code' => 'PRE005', 'procedure' => 'Fissure sealant', 'service' => 'Preventif', 'category' => 'preventif', 'price' => 75000, 'bhp_amount' => 20000, 'doctor_fee' => 25000, 'clinic_fee' => 30000],
            ['code' => 'KON001', 'procedure' => 'Tambal sementara', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 60000, 'bhp_amount' => 15000, 'doctor_fee' => 25000, 'clinic_fee' => 20000],
            ['code' => 'KON002', 'procedure' => 'Tambal GIC kecil', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 75000, 'bhp_amount' => 20000, 'doctor_fee' => 30000, 'clinic_fee' => 25000],
            ['code' => 'KON003', 'procedure' => 'Tambal GIC besar', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 100000, 'bhp_amount' => 30000, 'doctor_fee' => 40000, 'clinic_fee' => 30000],
            ['code' => 'KON004', 'procedure' => 'Tambal komposit kecil', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 125000, 'bhp_amount' => 35000, 'doctor_fee' => 50000, 'clinic_fee' => 40000],
            ['code' => 'KON005', 'procedure' => 'Tambal komposit sedang', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 150000, 'bhp_amount' => 45000, 'doctor_fee' => 60000, 'clinic_fee' => 45000],
            ['code' => 'KON006', 'procedure' => 'Tambal komposit besar', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 200000, 'bhp_amount' => 60000, 'doctor_fee' => 80000, 'clinic_fee' => 60000],
            ['code' => 'KON007', 'procedure' => 'Pulp capping', 'service' => 'Konservasi', 'category' => 'konservasi', 'price' => 75000, 'bhp_amount' => 20000, 'doctor_fee' => 30000, 'clinic_fee' => 25000],
            ['code' => 'END001', 'procedure' => 'Trepanasi / open bur', 'service' => 'Endodontik', 'category' => 'endodonti', 'price' => 50000, 'bhp_amount' => 10000, 'doctor_fee' => 25000, 'clinic_fee' => 15000],
            ['code' => 'END002', 'procedure' => 'Devitalisasi pulpa', 'service' => 'Endodontik', 'category' => 'endodonti', 'price' => 75000, 'bhp_amount' => 20000, 'doctor_fee' => 35000, 'clinic_fee' => 20000],
            ['code' => 'END003', 'procedure' => 'Pulpotomi', 'service' => 'Endodontik', 'category' => 'endodonti', 'price' => 150000, 'bhp_amount' => 45000, 'doctor_fee' => 65000, 'clinic_fee' => 40000],
            ['code' => 'END004', 'procedure' => 'Pulpektomi', 'service' => 'Endodontik', 'category' => 'endodonti', 'price' => 200000, 'bhp_amount' => 60000, 'doctor_fee' => 85000, 'clinic_fee' => 55000],
            ['code' => 'END005', 'procedure' => 'Perawatan saluran akar 1 saluran', 'service' => 'Endodontik', 'category' => 'endodonti', 'price' => 250000, 'bhp_amount' => 75000, 'doctor_fee' => 105000, 'clinic_fee' => 70000],
            ['code' => 'EXT001', 'procedure' => 'Pencabutan gigi sulung', 'service' => 'Ekstraksi', 'category' => 'ekstraksi', 'price' => 50000, 'bhp_amount' => 10000, 'doctor_fee' => 25000, 'clinic_fee' => 15000],
            ['code' => 'EXT002', 'procedure' => 'Pencabutan gigi permanen', 'service' => 'Ekstraksi', 'category' => 'ekstraksi', 'price' => 75000, 'bhp_amount' => 15000, 'doctor_fee' => 35000, 'clinic_fee' => 25000],
            ['code' => 'EXT003', 'procedure' => 'Pencabutan dengan komplikasi', 'service' => 'Ekstraksi', 'category' => 'ekstraksi', 'price' => 125000, 'bhp_amount' => 25000, 'doctor_fee' => 65000, 'clinic_fee' => 35000],
            ['code' => 'EXT004', 'procedure' => 'Odontektomi / gigi impaksi', 'service' => 'Bedah mulut', 'category' => 'bedah', 'price' => 300000, 'bhp_amount' => 80000, 'doctor_fee' => 150000, 'clinic_fee' => 70000],
            ['code' => 'BED001', 'procedure' => 'Insisi dan drainase abses', 'service' => 'Bedah mulut', 'category' => 'bedah', 'price' => 75000, 'bhp_amount' => 20000, 'doctor_fee' => 35000, 'clinic_fee' => 20000],
            ['code' => 'PER001', 'procedure' => 'Gingivektomi', 'service' => 'Periodontik', 'category' => 'periodonti', 'price' => 100000, 'bhp_amount' => 25000, 'doctor_fee' => 45000, 'clinic_fee' => 30000],
            ['code' => 'PER002', 'procedure' => 'Flap periodontal', 'service' => 'Periodontik', 'category' => 'periodonti', 'price' => 250000, 'bhp_amount' => 60000, 'doctor_fee' => 120000, 'clinic_fee' => 70000],
            ['code' => 'PRO001', 'procedure' => 'Gigi tiruan akrilik pertama', 'service' => 'Prostodontik', 'category' => 'prostodonti', 'price' => 400000, 'bhp_amount' => 150000, 'doctor_fee' => 100000, 'clinic_fee' => 150000],
            ['code' => 'PRO002', 'procedure' => 'Reparasi gigi tiruan', 'service' => 'Prostodontik', 'category' => 'prostodonti', 'price' => 150000, 'bhp_amount' => 50000, 'doctor_fee' => 40000, 'clinic_fee' => 60000],
            ['code' => 'PRO003', 'procedure' => 'Mahkota gigi', 'service' => 'Prostodontik', 'category' => 'prostodonti', 'price' => 600000, 'bhp_amount' => 200000, 'doctor_fee' => 150000, 'clinic_fee' => 250000],
        ];
    }

    /**
     * @return list<string>
     */
    public static function serviceNames(): array
    {
        return array_values(array_unique(array_column(self::items(), 'service')));
    }
}
