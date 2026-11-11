<?php

namespace App\Support\Access;

use App\Enums\RoleName;
use App\Enums\Workspace;
use App\Models\User;

/**
 * Memetakan user ke profil tampilan (sidebar/dashboard) tanpa mengubah permission.
 */
final class WorkspaceDetector
{
    public static function forUser(?User $user): Workspace
    {
        if (! $user) {
            return Workspace::Default;
        }

        if ($user->isPlatformAdmin() || $user->hasRole(RoleName::SuperAdminSaas)) {
            return Workspace::Platform;
        }

        if ($user->hasRole(RoleName::ClinicOps) || self::hasFrontlineOpsCapability($user)) {
            return Workspace::Operational;
        }

        if ($user->hasAnyRole([RoleName::Owner->value, RoleName::Manager->value])) {
            return Workspace::Admin;
        }

        if ($user->hasAnyRole([RoleName::Dentist->value, RoleName::DentalAssistant->value])) {
            return Workspace::Clinical;
        }

        return Workspace::Default;
    }

    /**
     * Kombinasi kapabilitas 4 fungsi frontline (tanpa harus punya role clinic-ops).
     * Owner/Manager dikecualikan agar tetap memakai workspace admin.
     */
    public static function hasFrontlineOpsCapability(User $user): bool
    {
        if ($user->hasAnyRole([RoleName::Owner->value, RoleName::Manager->value, RoleName::SuperAdminSaas->value])) {
            return false;
        }

        return $user->can('registration.view')
            && $user->can('queue.manage')
            && $user->can('pharmacy.view')
            && $user->can('billing.view');
    }

    public static function isOperational(?User $user): bool
    {
        return self::forUser($user) === Workspace::Operational;
    }
}
