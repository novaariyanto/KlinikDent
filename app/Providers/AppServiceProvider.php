<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashierShift;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Procedure;
use App\Models\ProcedureRecord;
use App\Models\PurchaseOrder;
use App\Models\Queue;
use App\Models\Referral;
use App\Models\Room;
use App\Models\SaasInvoice;
use App\Models\SaasPackage;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Visit;
use App\Policies\RolePolicy;
use App\Support\AppSettings;
use App\Support\ClinicSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('local')) {
            Model::preventSilentlyDiscardingAttributes();
        }

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->string('email')).'|'.$request->ip());
        });

        Gate::policy(Role::class, RolePolicy::class);

        Route::bind('doctor', fn (string $value) => Doctor::withoutGlobalScopes()->findOrFail($value));
        Route::bind('doctorSchedule', fn (string $value) => DoctorSchedule::withoutGlobalScopes()->findOrFail($value));
        Route::bind('user', fn (string $value) => User::withoutGlobalScopes()->findOrFail($value));
        Route::bind('branch', fn (string $value) => Branch::withoutGlobalScopes()->findOrFail($value));
        Route::bind('service', fn (string $value) => Service::withoutGlobalScopes()->findOrFail($value));
        Route::bind('procedure', fn (string $value) => Procedure::withoutGlobalScopes()->findOrFail($value));
        Route::bind('tariff', fn (string $value) => Tariff::withoutGlobalScopes()->findOrFail($value));
        Route::bind('invoice', fn (string $value) => Invoice::withoutGlobalScopes()->findOrFail($value));
        Route::bind('cashierShift', fn (string $value) => CashierShift::withoutGlobalScopes()->findOrFail($value));
        Route::bind('expense', fn (string $value) => Expense::withoutGlobalScopes()->findOrFail($value));
        Route::bind('expenseCategory', fn (string $value) => ExpenseCategory::withoutGlobalScopes()->findOrFail($value));
        Route::bind('cashAccount', fn (string $value) => CashAccount::withoutGlobalScopes()->findOrFail($value));
        Route::bind('medicine', fn (string $value) => Medicine::withoutGlobalScopes()->findOrFail($value));
        Route::bind('medicineStock', fn (string $value) => MedicineStock::withoutGlobalScopes()->findOrFail($value));
        Route::bind('supplier', fn (string $value) => Supplier::withoutGlobalScopes()->findOrFail($value));
        Route::bind('purchaseOrder', fn (string $value) => PurchaseOrder::withoutGlobalScopes()->findOrFail($value));
        Route::bind('payer', fn (string $value) => Payer::withoutGlobalScopes()->findOrFail($value));
        Route::bind('room', fn (string $value) => Room::withoutGlobalScopes()->findOrFail($value));
        Route::bind('patient', fn (string $value) => Patient::withoutGlobalScopes()->findOrFail($value));
        Route::bind('visit', fn (string $value) => Visit::withoutGlobalScopes()->findOrFail($value));
        Route::bind('queue', fn (string $value) => Queue::withoutGlobalScopes()->findOrFail($value));
        Route::bind('diagnosis', fn (string $value) => Diagnosis::withoutGlobalScopes()->findOrFail($value));
        Route::bind('procedure_record', fn (string $value) => ProcedureRecord::withoutGlobalScopes()->findOrFail($value));
        Route::bind('prescription', fn (string $value) => Prescription::withoutGlobalScopes()->findOrFail($value));
        Route::bind('prescriptionItem', fn (string $value) => PrescriptionItem::query()->with('prescription')->findOrFail($value));
        Route::bind('referral', fn (string $value) => Referral::withoutGlobalScopes()->findOrFail($value));
        Route::bind('saasPackage', fn (string $value) => SaasPackage::query()->findOrFail($value));
        Route::bind('saasInvoice', fn (string $value) => SaasInvoice::query()->findOrFail($value));
        Route::bind('insuranceClaim', fn (string $value) => InsuranceClaim::withoutGlobalScopes()->findOrFail($value));

        View::composer('*', function ($view) {
            try {
                if (current_tenant_id()) {
                    ClinicSettings::apply();
                    $appName = ClinicSettings::name();
                } else {
                    $appName = Setting::getValue('app_name', config('app.name'));
                }
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
