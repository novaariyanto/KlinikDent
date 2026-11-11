<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdminSaas = 'super-admin-saas';
    case Owner = 'owner';
    case Manager = 'manager';
    case Registration = 'registration';
    case ClinicOps = 'clinic-ops';
    case Dentist = 'dentist';
    case DentalAssistant = 'dental-assistant';
    case Nurse = 'nurse';
    case Pharmacy = 'pharmacy';
    case Cashier = 'cashier';
    case Finance = 'finance';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdminSaas => 'Super Admin SaaS',
            self::Owner => 'Owner / Super Admin Klinik',
            self::Manager => 'Manager / Admin Klinik',
            self::Registration => 'Pendaftaran',
            self::ClinicOps => 'Admin Operasional Klinik',
            self::Dentist => 'Dokter Gigi',
            self::DentalAssistant => 'Asisten Dokter',
            self::Nurse => 'Perawat',
            self::Pharmacy => 'Farmasi',
            self::Cashier => 'Kasir',
            self::Finance => 'Keuangan',
            self::Auditor => 'Auditor / Viewer',
        };
    }

    public function isPlatform(): bool
    {
        return $this === self::SuperAdminSaas;
    }

    public function isMedicalStaff(): bool
    {
        return in_array($this, [
            self::Dentist,
            self::DentalAssistant,
            self::Nurse,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function medicalStaffValues(): array
    {
        return [
            self::Dentist->value,
            self::DentalAssistant->value,
            self::Nurse->value,
        ];
    }

    public function dashboardTitle(): string
    {
        return match ($this) {
            self::SuperAdminSaas => 'Dashboard SaaS',
            self::Registration => 'Dashboard Pendaftaran',
            self::ClinicOps => 'Dashboard Operasional',
            self::Dentist => 'Dashboard Dokter',
            self::Pharmacy => 'Dashboard Farmasi',
            self::Cashier => 'Dashboard Kasir',
            self::Finance => 'Dashboard Keuangan',
            default => 'Dashboard',
        };
    }

    public function dashboardDescription(): string
    {
        return match ($this) {
            self::SuperAdminSaas => 'Ringkasan tenant, langganan, dan status klinik di platform.',
            self::Owner => 'Ringkasan klinik, laporan, dan konfigurasi.',
            self::Manager => 'Ringkasan operasional dan administrasi klinik.',
            self::Registration => 'Kunjungan, antrean, dan pendaftaran pasien hari ini.',
            self::ClinicOps => 'Pusat kerja pendaftaran, antrean, farmasi, dan kasir.',
            self::Dentist => 'Antrean dan pelayanan klinis pasien Anda.',
            self::DentalAssistant => 'Antrean dan dukungan pemeriksaan.',
            self::Nurse => 'Antrean, tanda vital, dan screening pasien.',
            self::Pharmacy => 'Resep masuk, stok, dan penyerahan obat.',
            self::Cashier => 'Tagihan, pembayaran, dan shift kasir.',
            self::Finance => 'Pendapatan, pengeluaran, dan laporan keuangan.',
            self::Auditor => 'Tampilan read-only untuk audit operasional.',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function tenantValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $role) => $role !== self::SuperAdminSaas->value
        ));
    }

    public static function isSystem(string $name): bool
    {
        return in_array($name, self::values(), true);
    }

    public static function tryFromUserRoles(iterable $names): ?self
    {
        foreach ($names as $name) {
            $role = self::tryFrom((string) $name);

            if ($role) {
                return $role;
            }
        }

        return null;
    }
}
