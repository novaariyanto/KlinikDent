<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'name', 'name' => 'name', 'title' => 'Name'],
            ['data' => 'email', 'name' => 'email', 'title' => 'Email'],
            ['data' => 'role', 'name' => 'roles.name', 'title' => 'Role', 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created At'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('users.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('roles');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('role', function (User $user) {
                return $user->roles
                    ->pluck('name')
                    ->map(fn (string $name) => '<span class="badge badge-soft-primary">'.e($name).'</span>')
                    ->implode(' ');
            })
            ->editColumn('status', function (User $user) {
                return '<span class="'.$user->status->badgeClass().'">'.e($user->status->label()).'</span>';
            })
            ->editColumn('created_at', fn (User $user) => $user->created_at?->format('d M Y H:i'))
            ->addColumn('action', fn (User $user) => view('users.partials.actions', compact('user'))->render())
            ->filterColumn('role', function ($query, $keyword) {
                $query->whereHas('roles', function ($roleQuery) use ($keyword) {
                    $roleQuery->where('name', 'like', "%{$keyword}%");
                });
            })
            ->rawColumns(['role', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => $this->assignableRoles(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => $data['status'],
        ]);

        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('roles', 'permissions');

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return view('users.edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own status.');
        }

        $user->update([
            'status' => $user->status === UserStatus::Active
                ? UserStatus::Inactive
                : UserStatus::Active,
        ]);

        return back()->with('success', 'User status updated successfully.');
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Password reset successfully.');
    }

    /**
     * @return Collection<int, Role>
     */
    protected function assignableRoles()
    {
        $roles = Role::query()->orderBy('name')->get();

        if (! auth()->user()?->isSuperAdmin()) {
            return $roles->reject(fn (Role $role) => $role->name === 'Super Admin');
        }

        return $roles;
    }
}
