<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ActivityLog::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Time'],
            ['data' => 'user', 'name' => 'user.name', 'title' => 'User'],
            ['data' => 'module', 'name' => 'module', 'title' => 'Module', 'className' => 'text-center'],
            ['data' => 'event', 'name' => 'event', 'title' => 'Event', 'className' => 'text-center'],
            ['data' => 'description', 'name' => 'description', 'title' => 'Description'],
            ['data' => 'ip_address', 'name' => 'ip_address', 'title' => 'IP'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('logs.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query()->with('user');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('created_at', fn (ActivityLog $log) => $log->created_at?->format('d M Y H:i:s'))
            ->addColumn('user', function (ActivityLog $log) {
                if (! $log->user) {
                    return '<span class="text-muted">System / Guest</span>';
                }

                return e($log->user->name).'<br><small class="text-muted">'.e($log->user->email).'</small>';
            })
            ->editColumn('module', fn (ActivityLog $log) => '<span class="badge badge-soft-primary">'.e($log->moduleLabel()).'</span>')
            ->editColumn('event', fn (ActivityLog $log) => '<span class="'.$log->eventBadgeClass().'">'.e($log->eventLabel()).'</span>')
            ->editColumn('ip_address', fn (ActivityLog $log) => $log->ip_address ? e($log->ip_address) : '<span class="text-muted">-</span>')
            ->addColumn('action', fn (ActivityLog $log) => view('logs.partials.actions', compact('log'))->render())
            ->filterColumn('user', function ($query, $keyword) {
                $query->whereHas('user', function ($userQuery) use ($keyword) {
                    $userQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['user', 'module', 'event', 'ip_address', 'action'])
            ->toJson();
    }

    public function show(ActivityLog $activityLog): View
    {
        $this->authorize('view', $activityLog);

        $activityLog->load(['user', 'subject']);

        return view('logs.show', [
            'log' => $activityLog,
        ]);
    }

    public function destroy(ActivityLog $activityLog): RedirectResponse
    {
        $this->authorize('delete', $activityLog);

        $activityLog->delete();

        return redirect()
            ->route('logs.index')
            ->with('success', 'Log entry deleted successfully.');
    }

    public function destroyAll(): RedirectResponse
    {
        $this->authorize('deleteAny', ActivityLog::class);

        ActivityLog::query()->delete();

        activity_log('deleted', null, [], 'Cleared all activity logs', 'logs');

        return redirect()
            ->route('logs.index')
            ->with('success', 'All activity logs were cleared.');
    }
}
