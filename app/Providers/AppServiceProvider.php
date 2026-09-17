<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Diagnosis;
use App\Models\Medicine;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\Queue;
use App\Models\Referral;
use App\Models\Room;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Visit;
use App\Policies\RolePolicy;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
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

        Route::bind('user', fn (string $value) => User::withoutGlobalScopes()->findOrFail($value));
        Route::bind('branch', fn (string $value) => Branch::withoutGlobalScopes()->findOrFail($value));
        Route::bind('service', fn (string $value) => Service::withoutGlobalScopes()->findOrFail($value));
        Route::bind('procedure', fn (string $value) => Procedure::withoutGlobalScopes()->findOrFail($value));
        Route::bind('tariff', fn (string $value) => Tariff::withoutGlobalScopes()->findOrFail($value));
        Route::bind('medicine', fn (string $value) => Medicine::withoutGlobalScopes()->findOrFail($value));
        Route::bind('payer', fn (string $value) => Payer::withoutGlobalScopes()->findOrFail($value));
        Route::bind('room', fn (string $value) => Room::withoutGlobalScopes()->findOrFail($value));
        Route::bind('patient', fn (string $value) => Patient::withoutGlobalScopes()->findOrFail($value));
        Route::bind('visit', fn (string $value) => Visit::withoutGlobalScopes()->findOrFail($value));
        Route::bind('queue', fn (string $value) => Queue::withoutGlobalScopes()->findOrFail($value));
        Route::bind('diagnosis', fn (string $value) => Diagnosis::withoutGlobalScopes()->findOrFail($value));
        Route::bind('procedure_record', fn (string $value) => ProcedureRecord::withoutGlobalScopes()->findOrFail($value));
        Route::bind('prescription', fn (string $value) => Prescription::withoutGlobalScopes()->findOrFail($value));
        Route::bind('referral', fn (string $value) => Referral::withoutGlobalScopes()->findOrFail($value));

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
            if (request()->routeIs('care.show')) {
                $view->with('sidebarMenus', collect());

                return;
            }

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
