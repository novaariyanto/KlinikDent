<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('dashboard.view'), 403);

        $stats = [
            'total_users' => User::query()->count(),
            'active_users' => User::query()->active()->count(),
            'inactive_users' => User::query()->where('status', UserStatus::Inactive)->count(),
            'total_records' => 1280,
            'transactions' => 346,
        ];

        return view('dashboard.index', compact('stats'));
    }
}
