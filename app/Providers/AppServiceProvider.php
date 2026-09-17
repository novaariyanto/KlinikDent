<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function ($user, string $ability) {
            if ($ability === 'impersonate') {
                return null;
            }

            if ($user instanceof User && $user->hasRole('Super Admin')) {
                return true;
            }

            return null;
        });

        View::composer('*', function ($view) {
            try {
                $appName = Setting::getValue('app_name', config('app.name'));
            } catch (\Throwable) {
                $appName = config('app.name');
            }

            $view->with('appName', $appName);
        });

        Mail::extend('smtp', function (array $config) {
            return AppSettings::createSmtpTransport($config);
        });

        try {
            AppSettings::applyMailConfig();
        } catch (\Throwable) {
            //
        }

        View::composer('layouts.sidebar', function ($view) {
            $user = auth()->user();

            try {
                $sidebarMenus = $user
                    ? Menu::sidebarFor($user)
                    : collect();
            } catch (\Throwable) {
                $sidebarMenus = collect();
            }

            $view->with('sidebarMenus', $sidebarMenus);
        });
    }
}
