<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Role'],
            ['data' => 'permissions', 'name' => 'permissions', 'title' => 'Permissions', 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('roles.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $query = Role::query()->with('permissions')->withCount('permissions');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('permissions', function (Role $role) {
                $count = $role->permissions_count;
                $badges = $role->permissions
                    ->take(3)
                    ->map(fn (Permission $permission) => '<span class="badge badge-soft-info">'.e($permission->name).'</span>')
                    ->implode(' ');

                if ($count > 3) {
                    $badges .= ' <span class="badge bg-secondary">+'.($count - 3).'</span>';
                }

                if ($count === 0) {
                    return '<span class="text-muted">No permissions</span>';
                }

                return $badges.' <small class="text-muted">('.$count.')</small>';
            })
            ->editColumn('created_at', fn (Role $role) => $role->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (Role $role) => view('roles.partials.actions', compact('role'))->render())
            ->rawColumns(['permissions', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('roles.create', [
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($request->validated('permissions', []));

        activity_log('created', $role, [
            'permissions' => $request->validated('permissions', []),
        ], 'Created role '.$role->name, 'roles');

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->load('permissions');

        return view('roles.edit', [
            'role' => $role,
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->name !== 'Super Admin') {
            $role->update(['name' => $request->validated('name')]);
        }

        $role->syncPermissions($request->validated('permissions', []));

        activity_log('updated', $role, [
            'permissions' => $request->validated('permissions', []),
        ], 'Updated role '.$role->name, 'roles');

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->name === 'Super Admin') {
            return back()->with('error', 'The Super Admin role cannot be deleted.');
        }

        activity_log('deleted', $role, ['name' => $role->name], 'Deleted role '.$role->name, 'roles');

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }

    /**
     * @return array<string, Collection<int, Permission>>
     */
    protected function groupedPermissions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => explode('.', $permission->name)[0])
            ->all();
    }
}
