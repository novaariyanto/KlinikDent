<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdminSaas = 'super-admin-saas';
    case Owner = 'owner';
    case Manager = 'manager';
    case Registration = 'registration';
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

    public function dashboardTitle(): string
    {
        return match ($this) {
            self::SuperAdminSaas => 'Dashboard SaaS',
            self::Registration => 'Dashboard Pendaftaran',
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
            self::SuperAdminSaas => 'Dashboard pengelolaan platform SaaS akan dikembangkan pada tahap berikutnya.',
            self::Owner => 'Dashboard pemilik klinik akan dikembangkan pada tahap berikutnya.',
            self::Manager => 'Dashboard operasional klinik akan dikembangkan pada tahap berikutnya.',
            self::Registration => 'Dashboard pendaftaran pasien akan dikembangkan pada tahap berikutnya.',
            self::Dentist => 'Dashboard pelayanan dokter akan dikembangkan pada tahap berikutnya.',
            self::DentalAssistant => 'Dashboard asisten dokter akan dikembangkan pada tahap berikutnya.',
            self::Nurse => 'Dashboard perawat akan dikembangkan pada tahap berikutnya.',
            self::Pharmacy => 'Dashboard farmasi akan dikembangkan pada tahap berikutnya.',
            self::Cashier => 'Dashboard kasir akan dikembangkan pada tahap berikutnya.',
            self::Finance => 'Dashboard keuangan akan dikembangkan pada tahap berikutnya.',
            self::Auditor => 'Dashboard auditor bersifat tampilan saja dan akan dikembangkan pada tahap berikutnya.',
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
