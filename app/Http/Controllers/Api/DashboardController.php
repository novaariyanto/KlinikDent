<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Enums\Workspace;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Support\Access\WorkspaceDetector;
use App\Support\Onboarding\SetupChecklistService;
use App\Support\Reports\DashboardMetricsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardMetricsService $metrics,
        protected SetupChecklistService $checklist,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->can('dashboard.view')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $active = $request->attributes->get('active_branch');
        if (! $active instanceof Branch) {
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
            $payload = $this->metrics->forUser($user, RoleName::ClinicOps, (int) $active->id);
            $description = 'Selamat datang. '.RoleName::ClinicOps->dashboardDescription();
        } else {
            $title = $primary?->dashboardTitle() ?? 'Dashboard';
            $payload = $this->metrics->forUser($user, null, (int) $active->id);
            $description = $primary
                ? 'Selamat datang. '.$primary->dashboardDescription()
                : 'Selamat datang. Ringkasan gabungan untuk '.$roles->map(fn (RoleName $role) => $role->label())->join(', ').'.';
        }

        $checklist = $this->checklist->forOwner($user);
        if (is_array($checklist)) {
            $checklist['items'] = array_map(function (array $item) {
                return [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'done' => (bool) $item['done'],
                    'optional' => (bool) $item['optional'],
                ];
            }, $checklist['items']);
        }

        return response()->json([
            'data' => [
                'title' => $title,
                'description' => $description,
                'workspace' => $workspace->value,
                'cards' => array_map(fn (array $card) => [
                    'label' => $card['label'],
                    'value' => $card['value'],
                ], $payload['cards']),
                'links' => array_map(fn (array $link) => [
                    'label' => $link['label'],
                    'route' => $link['route'],
                ], $payload['links']),
                'note' => $payload['note'],
                'setup_checklist' => $checklist,
                'branch' => [
                    'id' => $active->id,
                    'name' => $active->name,
                ],
            ],
        ]);
    }
}
