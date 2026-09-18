<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\Reports\DashboardMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardMetricsService $dashboards): View
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->can('dashboard.view')) {
            abort(403);
        }

        $roles = $user->getRoleNames()
            ->map(fn (string $name) => RoleName::tryFrom($name))
            ->filter()
            ->unique()
            ->values();
        $primary = $roles->count() === 1 ? $roles->first() : null;
        $title = $primary?->dashboardTitle() ?? 'Dashboard';
        $metrics = $dashboards->forUser($user);

        $description = $primary
            ? 'Selamat datang. '.$primary->dashboardDescription()
            : 'Selamat datang. Ringkasan gabungan untuk '.$roles->map(fn (RoleName $role) => $role->label())->join(', ').'.';

        return view('dashboard.index', [
            'title' => $title,
            'cards' => $metrics['cards'],
            'links' => $metrics['links'],
            'note' => $metrics['note'],
            'icon' => $primary?->isPlatform() ? 'bx bx-buildings' : 'bx bx-home-circle',
            'description' => $description,
        ]);
    }
}
