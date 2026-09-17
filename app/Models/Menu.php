<?php

namespace App\Models;

use App\Enums\MenuType;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class Menu extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
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
            'sort_order' => 'integer',
            'type' => MenuType::class,
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
        Cache::forget('sidebar.menus');
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
        if ($this->route_name && request()->routeIs($this->route_name)) {
            return true;
        }

        if ($this->route_name) {
            $group = explode('.', $this->route_name)[0];

            if ($group !== '' && request()->routeIs($group.'.*')) {
                return true;
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

        if ($this->permission) {
            return $user->can($this->permission);
        }

        return $this->userCanAccessRoute($user);
    }

    /**
     * @return Collection<int, Menu>
     */
    public static function sidebarFor(?User $user): Collection
    {
        /** @var Collection<int, Menu> $menus */
        $menus = Cache::rememberForever('sidebar.menus', function () {
            return static::query()
                ->with([
                    'children' => fn ($query) => $query
                        ->active()
                        ->orderBy('sort_order')
                        ->orderBy('title')
                        ->with([
                            'children' => fn ($query) => $query
                                ->active()
                                ->orderBy('sort_order')
                                ->orderBy('title'),
                        ]),
                ])
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get();
        });

        return static::filterVisible($menus, $user);
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
