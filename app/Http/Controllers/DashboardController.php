<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\Workspace;
use App\Models\User;
use App\Support\Access\WorkspaceDetector;
use App\Support\Onboarding\SetupChecklistService;
use App\Support\Reports\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardMetricsService $dashboards, SetupChecklistService $checklist): View
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->can('dashboard.view')) {
            abort(403);
        }

        $workspace = WorkspaceDetector::forUser($user);
        $roles = $user->getRoleNames()
            ->map(fn (string $name) => RoleName::tryFrom($name))
            ->filter()
            ->unique()
            ->values();
        $primary = $roles->count() === 1 ? $roles->first() : null;

        if ($workspace === Workspace::Operational) {
            $title = RoleName::ClinicOps->dashboardTitle();
            $metrics = $dashboards->forUser($user, RoleName::ClinicOps);
            $description = 'Selamat datang. '.RoleName::ClinicOps->dashboardDescription();
            $icon = 'bx bx-grid-alt';
        } else {
            $title = $primary?->dashboardTitle() ?? 'Dashboard';
            $metrics = $dashboards->forUser($user);
            $description = $primary
                ? 'Selamat datang. '.$primary->dashboardDescription()
                : 'Selamat datang. Ringkasan gabungan untuk '.$roles->map(fn (RoleName $role) => $role->label())->join(', ').'.';
            $icon = $primary?->isPlatform() ? 'bx bx-buildings' : 'bx bx-home-circle';
        }

        return view('dashboard.index', [
            'title' => $title,
            'cards' => $metrics['cards'],
            'links' => $metrics['links'],
            'note' => $metrics['note'],
            'icon' => $icon,
            'description' => $description,
            'workspace' => $workspace,
            'setupChecklist' => $checklist->forOwner($user),
        ]);
    }
}
