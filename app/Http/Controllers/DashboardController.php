<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        $role = auth()->user()?->primaryRole() ?? RoleName::tryFrom(
            (string) auth()->user()?->getRoleNames()->first()
        );

        $title = $role?->dashboardTitle() ?? 'Dashboard';
        $description = $role
            ? 'Selamat datang. '.$role->dashboardDescription()
            : 'Selamat datang. Dashboard akan dikembangkan pada tahap berikutnya.';
        $icon = $role?->isPlatform() ? 'bx bx-buildings' : 'bx bx-home-circle';
        $registrationStats = null;

        if ($role === RoleName::Registration) {
            $today = Visit::query()->visibleTo(auth()->user())->today();
            $registrationStats = [
                'visits_today' => (clone $today)->count(),
                'waiting' => (clone $today)->where('status', VisitStatus::Waiting)->count(),
                'in_service' => (clone $today)->where('status', VisitStatus::InService)->count(),
                'done' => (clone $today)->where('status', VisitStatus::Done)->count(),
            ];
        }

        return view('dashboard.index', compact('title', 'description', 'icon', 'registrationStats'));
    }
}
