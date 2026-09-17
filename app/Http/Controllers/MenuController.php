<?php

namespace App\Http\Controllers;

use App\Enums\MenuType;
use App\Enums\UserStatus;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class MenuController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Menu::class);

        $columns = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '60px'],
            ['data' => 'title', 'name' => 'title', 'title' => 'Title'],
            ['data' => 'icon', 'name' => 'icon', 'title' => 'Icon', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
            ['data' => 'type', 'name' => 'type', 'title' => 'Type', 'className' => 'text-center'],
            ['data' => 'parent', 'name' => 'parent.title', 'title' => 'Parent'],
            ['data' => 'route', 'name' => 'route_name', 'title' => 'Route / URL'],
            ['data' => 'permission', 'name' => 'permission', 'title' => 'Permission'],
            ['data' => 'sort_order', 'name' => 'sort_order', 'title' => 'Order', 'className' => 'text-center'],
            ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '120px'],
        ];

        return view('menus.index', compact('columns'));
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', Menu::class);

        $query = Menu::query()->with('parent');

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('icon', function (Menu $menu) {
                if (! $menu->icon) {
                    return '<span class="text-muted">-</span>';
                }

                return '<i class="'.e($menu->icon).' font-size-18"></i>';
            })
            ->editColumn('type', function (Menu $menu) {
                return '<span class="'.$menu->type->badgeClass().'">'.e($menu->type->label()).'</span>';
            })
            ->addColumn('parent', function (Menu $menu) {
                return $menu->parent
                    ? e($menu->parent->title)
                    : '<span class="text-muted">-</span>';
            })
            ->addColumn('route', function (Menu $menu) {
                if ($menu->route_name) {
                    return '<code>'.e($menu->route_name).'</code>';
                }

                if ($menu->url) {
                    return e($menu->url);
                }

                return '<span class="text-muted">-</span>';
            })
            ->editColumn('permission', function (Menu $menu) {
                if (! $menu->permission) {
                    return '<span class="text-muted">All users</span>';
                }

                return '<span class="badge badge-soft-info">'.e($menu->permission).'</span>';
            })
            ->editColumn('status', function (Menu $menu) {
                return '<span class="'.$menu->status->badgeClass().'">'.e($menu->status->label()).'</span>';
            })
            ->addColumn('action', fn (Menu $menu) => view('menus.partials.actions', compact('menu'))->render())
            ->rawColumns(['icon', 'type', 'parent', 'route', 'permission', 'status', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('create', Menu::class);

        return view('menus.create', $this->formData());
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        $menu = Menu::query()->create($request->validated());

        activity_log('created', $menu, $request->validated(), 'Created menu '.$menu->title, 'menus');

        return redirect()
            ->route('menus.index')
            ->with('success', 'Menu created successfully.');
    }

    public function edit(Menu $menu): View
    {
        $this->authorize('update', $menu);

        return view('menus.edit', $this->formData($menu));
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        activity_log('updated', $menu, $request->validated(), 'Updated menu '.$menu->title, 'menus');

        return redirect()
            ->route('menus.index')
            ->with('success', 'Menu updated successfully.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $this->authorize('delete', $menu);

        if ($menu->children()->exists()) {
            return back()->with('error', 'Move or delete child menus first.');
        }

        activity_log('deleted', $menu, ['title' => $menu->title], 'Deleted menu '.$menu->title, 'menus');

        $menu->delete();

        return redirect()
            ->route('menus.index')
            ->with('success', 'Menu deleted successfully.');
    }

    public function toggleStatus(Menu $menu): RedirectResponse
    {
        $this->authorize('toggleStatus', $menu);

        $menu->update([
            'status' => $menu->status === UserStatus::Active
                ? UserStatus::Inactive
                : UserStatus::Active,
        ]);

        activity_log('status_changed', $menu, [
            'status' => $menu->status->value,
        ], 'Changed status of menu '.$menu->title.' to '.$menu->status->label(), 'menus');

        return back()->with('success', 'Menu status updated successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(?Menu $menu = null): array
    {
        $parents = Menu::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->when($menu, function ($query) use ($menu) {
                $query->where('id', '!=', $menu->id)
                    ->whereNotIn('id', $menu->descendantIds());
            })
            ->get();

        return [
            'menu' => $menu,
            'parents' => $parents,
            'types' => MenuType::cases(),
            'statuses' => UserStatus::cases(),
            'permissions' => Permission::query()->orderBy('name')->pluck('name', 'name'),
        ];
    }
}
