<?php

namespace App\Support\Access;

use App\Enums\RoleName;

final class PermissionCatalog
{
    public const SAAS_ACCESS = 'saas.access';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            self::platform(),
            self::existing(),
            self::tenant(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function platform(): array
    {
        return [
            self::SAAS_ACCESS,
            'saas.dashboard.view',
            'tenant.view',
            'tenant.create',
            'tenant.update',
            'tenant.manage',
            'saas.activity.view',
            'package.view',
            'package.manage',
            'subscription.view',
            'subscription.manage',
            'invoice.view',
            'system.view',
            'system.manage',
            'master_global.view',
            'master_global.manage',
            'saas.integration.view',
            'saas.integration.manage',
            'system_log.view',
            'saas.audit.view',
            'support.view',
            'support.manage',
            'ticket.view',
            'ticket.manage',
            'platform.setting.view',
            'platform.setting.manage',
        ];
    }

    /**
     * @return list<string>
     */
    public static function existing(): array
    {
        return [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.impersonate',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'menus.view',
            'menus.create',
            'menus.edit',
            'menus.delete',
            'settings.update',
            'logs.view',
            'logs.delete',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tenant(): array
    {
        return [
            'patient.view',
            'patient.create',
            'patient.update',
            'patient.delete',
            'registration.view',
            'registration.create',
            'registration.update',
            'registration.cancel',
            'visit.view',
            'queue.view',
            'queue.manage',
            'medical_record.view',
            'medical_record.create',
            'medical_record.update',
            'odontogram.view',
            'odontogram.manage',
            'diagnosis.view',
            'diagnosis.manage',
            'procedure.view',
            'procedure.manage',
            'prescription.view',
            'prescription.create',
            'prescription.update',
            'referral.view',
            'referral.manage',
            'examination.view',
            'examination.manage',
            'anamnesis.view',
            'anamnesis.manage',
            'vital_sign.view',
            'vital_sign.manage',
            'care_note.view',
            'care_note.manage',
            'pharmacy.view',
            'pharmacy.manage',
            'medicine.view',
            'medicine.manage',
            'stock.view',
            'stock.manage',
            'purchase.view',
            'purchase.manage',
            'supplier.view',
            'supplier.manage',
            'billing.view',
            'billing.create',
            'billing.update',
            'billing.void',
            'payment.view',
            'payment.create',
            'receivable.view',
            'payable.view',
            'cash_shift.view',
            'cash_shift.manage',
            'finance.view',
            'finance.manage',
            'revenue.view',
            'expense.view',
            'expense.manage',
            'cash_bank.view',
            'cash_bank.manage',
            'report.view',
            'doctor.view',
            'doctor.manage',
            'staff.view',
            'staff.manage',
            'schedule.view',
            'schedule.manage',
            'room.view',
            'room.manage',
            'branch.view',
            'branch.manage',
            'clinic.view',
            'clinic.manage',
            'service.view',
            'service.manage',
            'tariff.view',
            'tariff.manage',
            'payer.view',
            'integration.view',
            'integration.manage',
            'satusehat.view',
            'bpjs.view',
            'audit_log.view',
            'setting.view',
            'setting.manage',
            'patient_history.view',
        ];
    }

    /**
     * @return list<string>
     */
    public static function forRole(RoleName|string $role): array
    {
        $role = $role instanceof RoleName ? $role : RoleName::from($role);

        return match ($role) {
            RoleName::SuperAdminSaas => self::forSuperAdminSaas(),
            RoleName::Owner => self::forOwner(),
            RoleName::Manager => self::forManager(),
            RoleName::Registration => self::forRegistration(),
            RoleName::Dentist => self::forDentist(),
            RoleName::DentalAssistant => self::forDentalAssistant(),
            RoleName::Nurse => self::forNurse(),
            RoleName::Pharmacy => self::forPharmacy(),
            RoleName::Cashier => self::forCashier(),
            RoleName::Finance => self::forFinance(),
            RoleName::Auditor => self::forAuditor(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roleMap(): array
    {
        $map = [];

        foreach (RoleName::cases() as $role) {
            $map[$role->value] = self::forRole($role);
        }

        return $map;
    }

    public static function isWritePermission(string $permission): bool
    {
        foreach (['.create', '.update', '.delete', '.manage', '.edit', '.cancel', '.impersonate', '.void'] as $suffix) {
            if (str_ends_with($permission, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected static function forSuperAdminSaas(): array
    {
        return array_values(array_unique(array_merge(self::platform(), self::existing(), [
            'branch.view',
            'branch.manage',
        ])));
    }

    /**
     * @return list<string>
     */
    protected static function forOwner(): array
    {
        return array_values(array_unique(array_merge(self::tenant(), [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.impersonate',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'settings.update',
            'logs.view',
            'logs.delete',
        ])));
    }

    /**
     * @return list<string>
     */
    protected static function forManager(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'patient.create',
            'patient.update',
            'patient.delete',
            'registration.view',
            'registration.create',
            'registration.update',
            'registration.cancel',
            'visit.view',
            'queue.view',
            'queue.manage',
            'pharmacy.view',
            'pharmacy.manage',
            'medicine.view',
            'medicine.manage',
            'stock.view',
            'stock.manage',
            'billing.view',
            'billing.create',
            'billing.update',
            'billing.void',
            'payment.view',
            'payment.create',
            'receivable.view',
            'report.view',
            'doctor.view',
            'doctor.manage',
            'schedule.view',
            'schedule.manage',
            'room.view',
            'room.manage',
            'clinic.view',
            'clinic.manage',
            'service.view',
            'service.manage',
            'procedure.view',
            'procedure.manage',
            'tariff.view',
            'tariff.manage',
            'payer.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'roles.view',
            'branch.view',
            'branch.manage',
            'integration.view',
            'satusehat.view',
            'bpjs.view',
            'audit_log.view',
            'logs.view',
            'setting.view',
            'setting.manage',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forRegistration(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'patient.create',
            'patient.update',
            'registration.view',
            'registration.create',
            'registration.update',
            'registration.cancel',
            'visit.view',
            'queue.view',
            'schedule.view',
            'payer.view',
            'bpjs.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forDentist(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'patient_history.view',
            'queue.view',
            'queue.manage',
            'examination.view',
            'examination.manage',
            'medical_record.view',
            'medical_record.create',
            'medical_record.update',
            'odontogram.view',
            'odontogram.manage',
            'diagnosis.view',
            'diagnosis.manage',
            'procedure.view',
            'procedure.manage',
            'prescription.view',
            'prescription.create',
            'prescription.update',
            'referral.view',
            'referral.manage',
            'schedule.view',
            'report.view',
            'satusehat.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forDentalAssistant(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'patient_history.view',
            'queue.view',
            'examination.view',
            'examination.manage',
            'anamnesis.view',
            'anamnesis.manage',
            'medical_record.view',
            'medical_record.create',
            'medical_record.update',
            'odontogram.view',
            'odontogram.manage',
            'procedure.view',
            'procedure.manage',
            'schedule.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forNurse(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'patient_history.view',
            'queue.view',
            'examination.view',
            'vital_sign.view',
            'vital_sign.manage',
            'anamnesis.view',
            'anamnesis.manage',
            'care_note.view',
            'care_note.manage',
            'schedule.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forPharmacy(): array
    {
        return [
            'dashboard.view',
            'prescription.view',
            'prescription.update',
            'pharmacy.view',
            'pharmacy.manage',
            'medicine.view',
            'medicine.manage',
            'stock.view',
            'stock.manage',
            'purchase.view',
            'purchase.manage',
            'supplier.view',
            'supplier.manage',
            'report.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forCashier(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'billing.view',
            'billing.create',
            'billing.update',
            'payment.view',
            'payment.create',
            'receivable.view',
            'cash_shift.view',
            'cash_shift.manage',
            'report.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forFinance(): array
    {
        return [
            'dashboard.view',
            'finance.view',
            'finance.manage',
            'revenue.view',
            'expense.view',
            'expense.manage',
            'receivable.view',
            'payable.view',
            'cash_bank.view',
            'cash_bank.manage',
            'supplier.view',
            'report.view',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function forAuditor(): array
    {
        return [
            'dashboard.view',
            'patient.view',
            'registration.view',
            'visit.view',
            'medical_record.view',
            'finance.view',
            'revenue.view',
            'expense.view',
            'report.view',
            'audit_log.view',
            'logs.view',
        ];
    }
}
