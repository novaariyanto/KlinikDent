<?php

namespace App\Models;

use App\Enums\MenuScope;
use App\Enums\MenuType;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Menu extends Model
{
    private const CATALOG_CACHE_KEY = 'sidebar.menus.v5';

    private const FILTERED_KEYS_CACHE_KEY = 'sidebar.filtered.keys.v5';

    protected $fillable = [
        'parent_id',
        'name',
        'title',
        'description',
        'scope',
        'tenant_id',
        'icon',
        'type',
        'route_name',
        'url',
        'permission',
        'sort_order',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'tenant_id' => 'integer',
            'sort_order' => 'integer',
            'type' => MenuType::class,
            'scope' => MenuScope::class,
            'status' => UserStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CATALOG_CACHE_KEY);

        foreach (Cache::pull(self::FILTERED_KEYS_CACHE_KEY, []) as $key) {
            Cache::forget($key);
        }

        if (! app()->bound('request')) {
            return;
        }

        foreach (request()->attributes->keys() as $attribute) {
            if (is_string($attribute) && str_starts_with($attribute, 'sidebar.for.')) {
                request()->attributes->remove($attribute);
            }
        }
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'menu_role')->withTimestamps();
    }

    /**
     * @param  Builder<Menu>  $query
     * @return Builder<Menu>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    public function href(): string
    {
        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?: 'javascript:void(0);';
    }

    public function isCurrent(): bool
    {
        if ($this->route_name) {
            if (request()->routeIs($this->route_name)) {
                return true;
            }

            $action = Str::afterLast($this->route_name, '.');

            if (in_array($action, ['index', 'create', 'show', 'edit', 'data'], true)) {
                $prefix = Str::beforeLast($this->route_name, '.');

                if ($prefix !== $this->route_name && request()->routeIs($prefix.'.*')) {
                    return true;
                }
            }
        }

        if ($this->url && $this->url !== '#' && request()->is(ltrim($this->url, '/'))) {
            return true;
        }

        return $this->children->contains(fn (self $child) => $child->isCurrent());
    }

    public function isVisibleTo(?User $user): bool
    {
        if ($this->status !== UserStatus::Active || ! $user) {
            return false;
        }

        if ($this->relationLoaded('roles') && $this->roles->isNotEmpty()) {
            if (! $user->hasAnyRole($this->roles->pluck('name')->all())) {
                return false;
            }
        }

        if ($this->permission) {
            return $user->can($this->permission);
        }

        return $this->userCanAccessRoute($user);
    }

    /**
     * @return Collection<int, Menu>
     */
    public function breadcrumbTrail(): Collection
    {
        $trail = new Collection;
        $current = $this;

        while ($current) {
            $trail->prepend($current);
            $current = $current->parent;
        }

        return $trail->values();
    }

    /**
     * @return array<string, string|null>
     */
    public function breadcrumb(): array
    {
        $items = [
            'Dashboard' => Route::has('dashboard') ? route('dashboard') : '/',
        ];

        foreach ($this->breadcrumbTrail() as $menu) {
            if ($menu->route_name === 'dashboard') {
                continue;
            }

            $url = $menu->route_name && Route::has($menu->route_name)
                ? route($menu->route_name)
                : null;

            $items[$menu->title] = $url;
        }

        $keys = array_keys($items);
        $last = end($keys);

        if ($last !== false) {
            $items[$last] = null;
        }

        return $items;
    }

    /**
     * @return Collection<int, Menu>
     */
    public static function sidebarFor(?User $user): Collection
    {
        if (! $user) {
            return new Collection;
        }

        $key = static::filteredCacheKey($user);

        if (request()->attributes->has($key)) {
            /** @var Collection<int, Menu> $memoized */
            $memoized = request()->attributes->get($key);

            return $memoized;
        }

        $filtered = static::rememberFiltered($key, function () use ($user) {
            return static::organizeSidebar(
                static::collapseDuplicateRoutes(static::filterVisible(static::catalog(), $user))
            );
        });

        request()->attributes->set($key, $filtered);

        return $filtered;
    }

    /**
     * @return Collection<int, Menu>
     */
    protected static function catalog(): Collection
    {
        /** @var Collection<int, Menu> $menus */
        $menus = Cache::rememberForever(self::CATALOG_CACHE_KEY, function () {
            return static::query()
                ->with([
                    'roles:id,name',
                    'children' => fn ($query) => $query
                        ->active()
                        ->orderBy('sort_order')
                        ->orderBy('title')
                        ->with([
                            'roles:id,name',
                            'children' => fn ($query) => $query
                                ->active()
                                ->orderBy('sort_order')
                                ->orderBy('title')
                                ->with('roles:id,name'),
                        ]),
                ])
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();
        });

        return $menus;
    }

    protected static function filteredCacheKey(User $user): string
    {
        $fingerprint = sha1(
            $user->getRoleNames()->sort()->implode('|')
            .'#'.
            $user->getAllPermissions()->pluck('name')->sort()->implode('|')
        );

        return 'sidebar.for.v5.'.$fingerprint;
    }

    /**
     * @param  callable(): Collection<int, Menu>  $callback
     * @return Collection<int, Menu>
     */
    protected static function rememberFiltered(string $key, callable $callback): Collection
    {
        $keys = Cache::get(self::FILTERED_KEYS_CACHE_KEY, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::forever(self::FILTERED_KEYS_CACHE_KEY, $keys);
        }

        /** @var Collection<int, Menu> $menus */
        $menus = Cache::rememberForever($key, $callback);

        return $menus;
    }

    public static function findForRoute(?string $routeName, ?User $user = null): ?self
    {
        if (! $routeName) {
            return null;
        }

        $query = static::query()
            ->with('parent.parent.parent')
            ->where('route_name', $routeName)
            ->active();

        if ($user) {
            $roleNames = $user->getRoleNames();

            $assigned = (clone $query)
                ->whereHas('roles', fn ($roles) => $roles->whereIn('name', $roleNames))
                ->orderBy('sort_order')
                ->first();

            if ($assigned) {
                return $assigned;
            }
        }

        return $query->orderBy('sort_order')->first();
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    protected static function filterVisible(Collection $menus, ?User $user): Collection
    {
        return $menus
            ->map(function (self $menu) use ($user) {
                $copy = clone $menu;
                $copy->setRelation('children', static::filterVisible($menu->children, $user));

                return $copy;
            })
            ->filter(function (self $menu) use ($user) {
                if (! $menu->isVisibleTo($user)) {
                    return false;
                }

                if ($menu->type === MenuType::Heading || $menu->children->isNotEmpty()) {
                    return $menu->children->isNotEmpty();
                }

                return true;
            })
            ->values();
    }

    /**
     * Gabungkan sibling yang sama: route_name identik, atau grup/heading dengan judul sama.
     * User multi-role (mis. Pendaftaran + Perawat) tidak melihat "Antrean" dua kali.
     *
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    protected static function collapseDuplicateRoutes(Collection $menus): Collection
    {
        $result = new Collection;
        $keepers = [];

        foreach ($menus as $menu) {
            $copy = clone $menu;

            if ($copy->relationLoaded('children')) {
                $copy->setRelation('children', static::collapseDuplicateRoutes($copy->children));
            }

            $key = static::collapseKey($copy);

            if ($key === null) {
                $result->push($copy);

                continue;
            }

            if (! isset($keepers[$key])) {
                $keepers[$key] = $copy;
                $result->push($copy);

                continue;
            }

            $keeper = $keepers[$key];

            if ($copy->children->isNotEmpty()) {
                $keeper->setRelation(
                    'children',
                    static::collapseDuplicateRoutes($keeper->children->concat($copy->children)->values())
                );
            }

            if ($copy->route_name === 'dashboard' || $keeper->route_name === 'dashboard') {
                $keeper->title = 'Dashboard';
            } elseif (! filled($copy->route_name) && ! filled($keeper->route_name)) {
                $keeper->title = static::canonicalTitle(static::titleAlias((string) $keeper->title), (string) $keeper->title);
            }
        }

        return $result->values();
    }

    /**
     * Rapikan grup setelah collapse: serap item yatim, lalu buat heading jika belum ada.
     *
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    protected static function organizeSidebar(Collection $menus): Collection
    {
        $menus = static::absorbRelatedLeaves($menus);

        if ($menus->contains(fn (self $menu) => $menu->type === MenuType::Heading)) {
            return $menus->values();
        }

        return static::groupIntoHeadings($menus);
    }

    /**
     * Masukkan leaf yang route/judulnya sudah tercakup grup sibling, agar tidak jadi menu terpisah.
     *
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    protected static function absorbRelatedLeaves(Collection $menus): Collection
    {
        $groups = $menus->filter(fn (self $menu) => $menu->type !== MenuType::Heading && $menu->children->isNotEmpty());
        $kept = new Collection;

        foreach ($menus as $menu) {
            if ($menu->type === MenuType::Heading || $menu->children->isNotEmpty()) {
                $kept->push($menu);

                continue;
            }

            $host = $groups->first(function (self $group) use ($menu) {
                if (static::titleAlias((string) $group->title) === static::titleAlias((string) $menu->title)) {
                    return true;
                }

                if (! $menu->route_name) {
                    return false;
                }

                return $group->children->contains(
                    fn (self $child) => $child->route_name && $child->route_name === $menu->route_name
                );
            });

            if (! $host) {
                $kept->push($menu);

                continue;
            }

            if ($menu->route_name && $host->children->contains(
                fn (self $child) => $child->route_name === $menu->route_name
            )) {
                continue;
            }

            $host->setRelation('children', static::collapseDuplicateRoutes(
                $host->children->push($menu)->values()
            ));
        }

        return $kept->values();
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    protected static function groupIntoHeadings(Collection $menus): Collection
    {
        $order = ['Operasional', 'Pelayanan', 'Kasir', 'Farmasi', 'Laporan'];
        $buckets = [
            'dashboard' => new Collection,
            'Operasional' => new Collection,
            'Pelayanan' => new Collection,
            'Kasir' => new Collection,
            'Farmasi' => new Collection,
            'Laporan' => new Collection,
            '_rest' => new Collection,
        ];

        foreach ($menus as $menu) {
            if ($menu->route_name === 'dashboard' || str_starts_with(static::titleAlias((string) $menu->title), 'dashboard')) {
                $buckets['dashboard']->push($menu);

                continue;
            }

            $group = static::headingForTitle((string) $menu->title);

            if (
                $group
                && static::titleAlias((string) $menu->title) === static::titleAlias($group)
                && $menu->children->isNotEmpty()
            ) {
                foreach ($menu->children as $child) {
                    $buckets[$group]->push($child);
                }

                continue;
            }

            $buckets[$group ?? '_rest']->push($menu);
        }

        $result = $buckets['dashboard'];
        $sort = 10;

        foreach ($order as $title) {
            if ($buckets[$title]->isEmpty()) {
                continue;
            }

            $result->push(static::makeHeading($title, $buckets[$title], $sort));
            $sort += 10;
        }

        return $result->concat($buckets['_rest'])->values();
    }

    protected static function headingForTitle(string $title): ?string
    {
        $alias = static::titleAlias($title);

        return match (true) {
            in_array($alias, ['pendaftaran', 'antrean', 'jadwal', 'penjamin', 'cek bpjs', 'pasien'], true) => 'Operasional',
            in_array($alias, ['pelayanan', 'pemeriksaan'], true) => 'Pelayanan',
            in_array($alias, ['transaksi', 'shift kasir', 'billing', 'billing & kasir'], true) => 'Kasir',
            in_array($alias, ['resep', 'obat', 'pembelian', 'farmasi'], true) => 'Farmasi',
            str_starts_with($alias, 'laporan') => 'Laporan',
            default => null,
        };
    }

    /**
     * @param  Collection<int, Menu>  $children
     */
    protected static function makeHeading(string $title, Collection $children, int $sortOrder): self
    {
        $heading = new static([
            'title' => $title,
            'name' => 'sidebar.group.'.Str::slug($title),
            'type' => MenuType::Heading,
            'status' => UserStatus::Active,
            'sort_order' => $sortOrder,
            'permission' => null,
            'route_name' => null,
        ]);

        $heading->setRelation('children', $children->values());
        $heading->setRelation('roles', new Collection);

        return $heading;
    }

    protected static function collapseKey(self $menu): ?string
    {
        if (filled($menu->route_name)) {
            return 'route:'.$menu->route_name;
        }

        $alias = static::titleAlias((string) $menu->title);

        if ($alias === '') {
            return null;
        }

        if ($menu->type === MenuType::Heading) {
            return 'heading:'.$alias;
        }

        return 'title:'.$alias;
    }

    protected static function titleAlias(string $title): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $title) ?? $title));

        return match (true) {
            str_starts_with($normalized, 'dashboard') => 'dashboard',
            in_array($normalized, ['jadwal', 'jadwal dokter', 'jadwal saya'], true) => 'jadwal',
            default => $normalized,
        };
    }

    protected static function canonicalTitle(string $alias, string $fallback): string
    {
        return match ($alias) {
            'jadwal' => 'Jadwal',
            'dashboard' => 'Dashboard',
            default => $fallback,
        };
    }

    protected function userCanAccessRoute(User $user): bool
    {
        if (! $this->route_name || ! Route::has($this->route_name)) {
            return true;
        }

        $route = Route::getRoutes()->getByName($this->route_name);

        if (! $route) {
            return true;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if (str_starts_with($middleware, 'permission:')) {
                $permissions = explode('|', substr($middleware, strlen('permission:')));

                return collect($permissions)->contains(fn (string $permission) => $user->can($permission));
            }

            if (str_starts_with($middleware, 'role:')) {
                $roles = explode('|', substr($middleware, strlen('role:')));

                return $user->hasAnyRole($roles);
            }

            if (str_starts_with($middleware, 'role_or_permission:')) {
                $values = explode('|', substr($middleware, strlen('role_or_permission:')));

                return $user->hasAnyRole($values)
                    || collect($values)->contains(fn (string $value) => $user->can($value));
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children()->with('children')->get() as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
