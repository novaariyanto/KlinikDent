<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRoutePermission
{
    /**
     * @var array<string, string>
     */
    protected array $exact = [
        'integrations.satusehat' => 'satusehat.view',
        'integrations.satusehat.update' => 'integration.manage',
        'integrations.satusehat.send' => 'satusehat.view',
        'care.satusehat.send' => 'satusehat.view',
        'integrations.bpjs' => 'bpjs.view',
        'integrations.bpjs.update' => 'integration.manage',
        'integrations.bpjs.check' => 'bpjs.view',
        'registration.patients.bpjs' => 'bpjs.view',
        'integrations.claims.store' => 'integration.manage',
        'integrations.claims.download' => 'integration.view',
        'doctors.schedule' => 'schedule.view',
        'doctors.schedule.today' => 'schedule.view',
        'doctors.schedule.mine' => 'schedule.view',
        'doctors.schedule.create' => 'schedule.manage',
        'doctors.schedule.store' => 'schedule.manage',
        'doctors.schedules.store' => 'schedule.manage',
        'doctors.schedules.edit' => 'schedule.manage',
        'doctors.schedules.update' => 'schedule.manage',
        'doctors.schedules.destroy' => 'schedule.manage',
    ];

    /**
     * @var array<string, string>
     */
    protected array $prefixes = [
        'users.' => 'users.view',
        'branches.' => 'branch.view',
        'registration.' => 'registration.view',
        'patients.' => 'patient.view',
        'queue.' => 'queue.view',
        'care.' => 'examination.view',
        'examinations.' => 'examination.view',
        'medical-record.' => 'medical_record.view',
        'odontogram.' => 'odontogram.view',
        'diagnosis.' => 'diagnosis.view',
        'procedures.' => 'procedure.view',
        'prescriptions.' => 'prescription.view',
        'referrals.' => 'referral.view',
        'pharmacy.' => 'pharmacy.view',
        'billing.' => 'billing.view',
        'cashier.' => 'billing.view',
        'finance.' => 'finance.view',
        'reports.' => 'report.view',
        'doctors.' => 'doctor.view',
        'management.' => 'clinic.view',
        'settings.clinic' => 'setting.view',
        'roles.' => 'roles.view',
        'menus.' => 'menus.view',
        'logs.' => 'logs.view',
        'saas.' => 'saas.access',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $name = (string) $request->route()?->getName();

        if (! $user || $name === '' || $user->isPlatformAdmin()) {
            return $next($request);
        }

        $permission = $this->permissionFor($name);

        if ($permission) {
            abort_unless($user->can($permission), 403);
        }

        return $next($request);
    }

    protected function permissionFor(string $name): ?string
    {
        if (isset($this->exact[$name])) {
            return $this->exact[$name];
        }

        foreach ($this->prefixes as $prefix => $permission) {
            if (str_starts_with($name, $prefix)) {
                return $permission;
            }
        }

        return null;
    }
}
