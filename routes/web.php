<?php

use App\Enums\PayerType;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Clinical\CareController;
use App\Http\Controllers\Clinical\ClinicalIndexController;
use App\Http\Controllers\Clinical\PatientHistoryController;
use App\Http\Controllers\Clinical\PharmacyPrescriptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Management\ClinicController;
use App\Http\Controllers\Management\MedicineController;
use App\Http\Controllers\Management\PayerController;
use App\Http\Controllers\Management\ProcedureController;
use App\Http\Controllers\Management\RoomController;
use App\Http\Controllers\Management\ServiceController;
use App\Http\Controllers\Management\TariffController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\Registration\PatientController;
use App\Http\Controllers\Registration\QueueController;
use App\Http\Controllers\Registration\QueueMonitorController;
use App\Http\Controllers\Registration\VisitController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Saas\TenantController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/antrean/{token}', [QueueMonitorController::class, 'public'])->name('queue.monitor.public');
Route::get('/antrean/{token}/data', [QueueMonitorController::class, 'feed'])->name('queue.monitor.feed');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('tenant.scope')->group(function () {
        Route::get('/users/data', [UserController::class, 'data'])
            ->middleware('permission:users.view')
            ->name('users.data');
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->middleware('permission:users.edit')
            ->name('users.toggle-status');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('permission:users.edit')
            ->name('users.reset-password');
        Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])
            ->middleware('permission:users.impersonate')
            ->name('users.impersonate');
        Route::resource('users', UserController::class);

        Route::get('/branches/data', [BranchController::class, 'data'])
            ->middleware('permission:branch.view')
            ->name('branches.data');
        Route::resource('branches', BranchController::class);

        Route::prefix('management')->name('management.')->group(function () {
            Route::get('clinic', [ClinicController::class, 'index'])
                ->middleware('permission:clinic.view')
                ->name('clinic');

            Route::get('services/data', [ServiceController::class, 'data'])
                ->middleware('permission:service.view')
                ->name('services.data');
            Route::resource('services', ServiceController::class);

            Route::get('procedures/data', [ProcedureController::class, 'data'])
                ->middleware('permission:procedure.view')
                ->name('procedures.data');
            Route::resource('procedures', ProcedureController::class);

            Route::get('tariffs/data', [TariffController::class, 'data'])
                ->middleware('permission:tariff.view')
                ->name('tariffs.data');
            Route::resource('tariffs', TariffController::class);

            Route::get('medicines/data', [MedicineController::class, 'data'])
                ->middleware('permission:medicine.view')
                ->name('medicines.data');
            Route::resource('medicines', MedicineController::class);

            Route::get('payers/data', [PayerController::class, 'data'])
                ->middleware('permission:payer.view')
                ->name('payers.data');
            Route::resource('payers', PayerController::class);

            Route::get('rooms/data', [RoomController::class, 'data'])
                ->middleware('permission:room.view')
                ->name('rooms.data');
            Route::resource('rooms', RoomController::class);
        });

        Route::get('/pharmacy/medicines', [MedicineController::class, 'index'])
            ->middleware('permission:medicine.view')
            ->name('pharmacy.medicines.index');

        foreach (PayerType::cases() as $payerType) {
            Route::get('/registration/payers/'.$payerType->routeKey(), [PayerController::class, 'index'])
                ->middleware('permission:payer.view')
                ->defaults('payerType', $payerType->routeKey())
                ->name('registration.payers.'.$payerType->routeKey());
        }

        Route::get('/registration', [VisitController::class, 'hub'])->name('registration.index');
        Route::get('/registration/new', [VisitController::class, 'create'])->name('registration.new');
        Route::post('/registration/visits', [VisitController::class, 'store'])->name('registration.visits.store');
        Route::get('/registration/visits/data', [VisitController::class, 'data'])->name('registration.visits.data');
        Route::get('/registration/visits/today', [VisitController::class, 'index'])->name('registration.visits.today');
        Route::get('/registration/visits', [VisitController::class, 'index'])->name('registration.visits');
        Route::get('/registration/history', [VisitController::class, 'index'])->name('registration.history');
        Route::get('/patients/history', [PatientHistoryController::class, 'index'])->name('patients.history');
        Route::get('/patients/{patient}/history', [PatientHistoryController::class, 'show'])->name('patients.history.show');
        Route::get('/registration/visits/{visit}', [VisitController::class, 'show'])->name('registration.visits.show');
        Route::post('/registration/visits/{visit}/cancel', [VisitController::class, 'cancel'])->name('registration.visits.cancel');

        Route::get('/registration/patients/data', [PatientController::class, 'data'])->name('registration.patients.data');
        Route::get('/registration/patients/search', [PatientController::class, 'search'])->name('registration.patients.search');
        Route::get('/registration/patients/create', [PatientController::class, 'create'])->name('registration.patients.create');
        Route::get('/registration/patients', [PatientController::class, 'index'])->name('registration.patients');
        Route::post('/registration/patients', [PatientController::class, 'store'])->name('registration.patients.store');
        Route::get('/registration/patients/{patient}/edit', [PatientController::class, 'edit'])->name('registration.patients.edit');
        Route::get('/registration/patients/{patient}', [PatientController::class, 'show'])->name('registration.patients.show');
        Route::put('/registration/patients/{patient}', [PatientController::class, 'update'])->name('registration.patients.update');
        Route::delete('/registration/patients/{patient}', [PatientController::class, 'destroy'])->name('registration.patients.destroy');

        Route::get('/queue/data', [QueueController::class, 'data'])->name('queue.data');
        Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
        Route::get('/queue/today', [QueueController::class, 'index'])->name('queue.today');
        Route::get('/queue/mine', [QueueController::class, 'index'])->name('queue.mine');
        Route::get('/queue/waiting', [QueueController::class, 'index'])->name('queue.waiting');
        Route::get('/queue/called', [QueueController::class, 'index'])->name('queue.called');
        Route::get('/queue/monitor', [QueueMonitorController::class, 'show'])->name('queue.monitor');
        Route::post('/queue/{queue}/call', [QueueController::class, 'call'])->name('queue.call');
        Route::post('/queue/{queue}/complete', [QueueController::class, 'complete'])->name('queue.complete');
        Route::post('/queue/{queue}/skip', [QueueController::class, 'skip'])->name('queue.skip');

        Route::get('/medical-record', [ClinicalIndexController::class, 'index'])->name('medical-record.index');
        Route::get('/odontogram', [ClinicalIndexController::class, 'index'])->name('odontogram.index');
        Route::get('/diagnosis', [ClinicalIndexController::class, 'index'])->name('diagnosis.index');
        Route::get('/procedures', [ClinicalIndexController::class, 'index'])->name('procedures.index');
        Route::get('/prescriptions', [ClinicalIndexController::class, 'index'])->name('prescriptions.index');
        Route::get('/referrals', [ClinicalIndexController::class, 'index'])->name('referrals.index');
        Route::get('/examinations', [ClinicalIndexController::class, 'index'])->name('examinations.index');
        Route::get('/examinations/initial', [ClinicalIndexController::class, 'index'])->name('examinations.initial');
        Route::get('/examinations/anamnesis', [ClinicalIndexController::class, 'index'])->name('examinations.anamnesis');
        Route::get('/examinations/vitals', [ClinicalIndexController::class, 'index'])->name('examinations.vitals');
        Route::get('/examinations/notes', [ClinicalIndexController::class, 'index'])->name('examinations.notes');

        Route::get('/visits/{visit}/care', [CareController::class, 'show'])->name('care.show');
        Route::get('/visits/{visit}/care/diagnoses/search', [CareController::class, 'searchDiagnoses'])->name('care.diagnosis.search');
        Route::put('/visits/{visit}/care/record', [CareController::class, 'updateRecord'])->name('care.record.update');
        Route::put('/visits/{visit}/care/examination', [CareController::class, 'updateExamination'])->name('care.examination.update');
        Route::put('/visits/{visit}/care/vitals', [CareController::class, 'updateVitals'])->name('care.vitals.update');
        Route::put('/visits/{visit}/care/anamnesis', [CareController::class, 'updateAnamnesis'])->name('care.anamnesis.update');
        Route::put('/visits/{visit}/care/notes', [CareController::class, 'updateNotes'])->name('care.notes.update');
        Route::put('/visits/{visit}/care/tooth', [CareController::class, 'updateTooth'])->name('care.tooth.update');
        Route::put('/visits/{visit}/care/dental-exam', [CareController::class, 'updateDentalExam'])->name('care.dental-exam.update');
        Route::put('/visits/{visit}/care/systemic', [CareController::class, 'updateSystemicHistory'])->name('care.systemic.update');
        Route::post('/visits/{visit}/care/diagnoses', [CareController::class, 'storeDiagnosis'])->name('care.diagnosis.store');
        Route::delete('/visits/{visit}/care/diagnoses/{diagnosis}', [CareController::class, 'destroyDiagnosis'])->name('care.diagnosis.destroy');
        Route::post('/visits/{visit}/care/procedures', [CareController::class, 'storeProcedure'])->name('care.procedure.store');
        Route::delete('/visits/{visit}/care/procedures/{procedure_record}', [CareController::class, 'destroyProcedure'])->name('care.procedure.destroy');
        Route::post('/visits/{visit}/care/prescription-items', [CareController::class, 'storePrescriptionItem'])->name('care.prescription-item.store');
        Route::post('/visits/{visit}/care/prescriptions/{prescription}/send', [CareController::class, 'sendPrescription'])->name('care.prescription.send');
        Route::post('/visits/{visit}/care/referrals', [CareController::class, 'storeReferral'])->name('care.referral.store');
        Route::delete('/visits/{visit}/care/referrals/{referral}', [CareController::class, 'destroyReferral'])->name('care.referral.destroy');
        Route::post('/visits/{visit}/care/complete', [CareController::class, 'complete'])->name('care.complete');

        Route::get('/pharmacy/prescriptions/incoming', [PharmacyPrescriptionController::class, 'incoming'])->name('pharmacy.prescriptions.incoming');
        Route::get('/pharmacy/prescriptions/{prescription}', [PharmacyPrescriptionController::class, 'show'])->name('pharmacy.prescriptions.show');
    });

    Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonate'])
        ->name('impersonate.leave');

    Route::get('/roles/data', [RoleController::class, 'data'])
        ->middleware('permission:roles.view')
        ->name('roles.data');
    Route::resource('roles', RoleController::class);

    Route::get('/menus/data', [MenuController::class, 'data'])
        ->middleware('permission:menus.view')
        ->name('menus.data');
    Route::post('/menus/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])
        ->middleware('permission:menus.edit')
        ->name('menus.toggle-status');
    Route::resource('menus', MenuController::class)->except(['show']);

    Route::middleware('permission:settings.update')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-email', [SettingController::class, 'testEmail'])->name('settings.test-email');
    });

    Route::get('/logs/data', [ActivityLogController::class, 'data'])
        ->middleware('permission:logs.view')
        ->name('logs.data');
    Route::post('/logs/clear', [ActivityLogController::class, 'destroyAll'])
        ->middleware('permission:logs.delete')
        ->name('logs.clear');
    Route::get('/logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:logs.view')
        ->name('logs.index');
    Route::get('/logs/{activityLog}', [ActivityLogController::class, 'show'])
        ->middleware('permission:logs.view')
        ->name('logs.show');
    Route::delete('/logs/{activityLog}', [ActivityLogController::class, 'destroy'])
        ->middleware('permission:logs.delete')
        ->name('logs.destroy');

    Route::middleware('permission:saas.access')->prefix('saas')->name('saas.')->group(function () {
        Route::get('tenants/data', [TenantController::class, 'data'])
            ->middleware('permission:tenant.view')
            ->name('tenants.data');
        Route::post('tenants/{tenant}/status', [TenantController::class, 'toggleStatus'])
            ->middleware('permission:tenant.manage')
            ->name('tenants.status');
        Route::resource('tenants', TenantController::class);
    });

    require __DIR__.'/modules.php';
});
