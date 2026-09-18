<?php

use App\Enums\PayerType;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Billing\BillingDashboardController;
use App\Http\Controllers\Billing\CashShiftController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Clinical\CareController;
use App\Http\Controllers\Clinical\ClinicalIndexController;
use App\Http\Controllers\Clinical\PatientHistoryController;
use App\Http\Controllers\Clinical\PharmacyPrescriptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\Finance\CashBankController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\PayableController;
use App\Http\Controllers\Finance\ReceivableController;
use App\Http\Controllers\Finance\RevenueController;
use App\Http\Controllers\Integration\IntegrationController;
use App\Http\Controllers\Management\ClinicController;
use App\Http\Controllers\Management\MedicineController;
use App\Http\Controllers\Management\PayerController;
use App\Http\Controllers\Management\ProcedureController;
use App\Http\Controllers\Management\RoomController;
use App\Http\Controllers\Management\ServiceController;
use App\Http\Controllers\Management\TariffController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\Pharmacy\PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\PharmacyReportController;
use App\Http\Controllers\Pharmacy\PurchaseOrderController;
use App\Http\Controllers\Pharmacy\StockController;
use App\Http\Controllers\Pharmacy\SupplierController;
use App\Http\Controllers\Registration\PatientController;
use App\Http\Controllers\Registration\QueueController;
use App\Http\Controllers\Registration\QueueMonitorController;
use App\Http\Controllers\Registration\VisitController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Saas\InvoiceController as SaasInvoiceController;
use App\Http\Controllers\Saas\PackageController;
use App\Http\Controllers\Saas\PlatformOpsController;
use App\Http\Controllers\Saas\SubscriptionController;
use App\Http\Controllers\Saas\TenantController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Settings\ClinicSettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/antrean/{token}', [QueueMonitorController::class, 'public'])->name('queue.monitor.public');
Route::get('/antrean/{token}/data', [QueueMonitorController::class, 'feed'])->name('queue.monitor.feed');

Route::middleware(['auth', 'route.permission'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware(['tenant.scope', 'tenant.active'])->group(function () {
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

        Route::get('/doctors/data', [DoctorController::class, 'data'])
            ->middleware('permission:doctor.view')
            ->name('doctors.data');
        Route::get('/doctors/schedule/today', [DoctorScheduleController::class, 'today'])
            ->middleware('permission:schedule.view')
            ->name('doctors.schedule.today');
        Route::get('/doctors/schedule/mine', [DoctorScheduleController::class, 'mine'])
            ->middleware('permission:schedule.view')
            ->name('doctors.schedule.mine');
        Route::get('/doctors/schedule/create', [DoctorScheduleController::class, 'create'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedule.create');
        Route::get('/doctors/schedule', [DoctorScheduleController::class, 'index'])
            ->middleware('permission:schedule.view')
            ->name('doctors.schedule');
        Route::post('/doctors/schedules', [DoctorScheduleController::class, 'store'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedule.store');
        Route::post('/doctors/{doctor}/schedules', [DoctorScheduleController::class, 'store'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedules.store');
        Route::get('/doctors/schedules/{doctorSchedule}/edit', [DoctorScheduleController::class, 'edit'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedules.edit');
        Route::put('/doctors/schedules/{doctorSchedule}', [DoctorScheduleController::class, 'update'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedules.update');
        Route::delete('/doctors/schedules/{doctorSchedule}', [DoctorScheduleController::class, 'destroy'])
            ->middleware('permission:schedule.manage')
            ->name('doctors.schedules.destroy');
        Route::resource('doctors', DoctorController::class);

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
        Route::get('/registration/availability', [VisitController::class, 'availability'])->name('registration.availability');
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
        Route::delete('/visits/{visit}/care/tooth/{toothNumber}', [CareController::class, 'destroyTooth'])
            ->where('toothNumber', '[0-9]{2}')
            ->name('care.tooth.destroy');
        Route::put('/visits/{visit}/care/dental-exam', [CareController::class, 'updateDentalExam'])->name('care.dental-exam.update');
        Route::put('/visits/{visit}/care/systemic', [CareController::class, 'updateSystemicHistory'])->name('care.systemic.update');
        Route::post('/visits/{visit}/care/diagnoses', [CareController::class, 'storeDiagnosis'])->name('care.diagnosis.store');
        Route::put('/visits/{visit}/care/diagnoses/{diagnosis}', [CareController::class, 'updateDiagnosis'])->name('care.diagnosis.update');
        Route::delete('/visits/{visit}/care/diagnoses/{diagnosis}', [CareController::class, 'destroyDiagnosis'])->name('care.diagnosis.destroy');
        Route::post('/visits/{visit}/care/procedures', [CareController::class, 'storeProcedure'])->name('care.procedure.store');
        Route::delete('/visits/{visit}/care/procedures/{procedure_record}', [CareController::class, 'destroyProcedure'])->name('care.procedure.destroy');
        Route::post('/visits/{visit}/care/prescription-items', [CareController::class, 'storePrescriptionItem'])->name('care.prescription-item.store');
        Route::put('/visits/{visit}/care/prescription-items/{prescriptionItem}', [CareController::class, 'updatePrescriptionItem'])->name('care.prescription-item.update');
        Route::delete('/visits/{visit}/care/prescription-items/{prescriptionItem}', [CareController::class, 'destroyPrescriptionItem'])->name('care.prescription-item.destroy');
        Route::post('/visits/{visit}/care/prescriptions/{prescription}/send', [CareController::class, 'sendPrescription'])->name('care.prescription.send');
        Route::post('/visits/{visit}/care/referrals', [CareController::class, 'storeReferral'])->name('care.referral.store');
        Route::put('/visits/{visit}/care/referrals/{referral}', [CareController::class, 'updateReferral'])->name('care.referral.update');
        Route::delete('/visits/{visit}/care/referrals/{referral}', [CareController::class, 'destroyReferral'])->name('care.referral.destroy');
        Route::get('/visits/{visit}/care/referrals/{referral}/pdf', [CareController::class, 'printReferral'])->name('care.referral.pdf');
        Route::get('/visits/{visit}/care/instructions/pdf', [CareController::class, 'printInstructions'])->name('care.instructions.pdf');
        Route::post('/visits/{visit}/care/complete', [CareController::class, 'complete'])->name('care.complete');

        Route::get('/pharmacy', [PharmacyDashboardController::class, 'index'])->name('pharmacy.index');
        Route::get('/pharmacy/prescriptions/incoming', [PharmacyPrescriptionController::class, 'incoming'])->name('pharmacy.prescriptions.incoming');
        Route::get('/pharmacy/prescriptions/processing', [PharmacyPrescriptionController::class, 'processing'])->name('pharmacy.prescriptions.processing');
        Route::get('/pharmacy/prescriptions/completed', [PharmacyPrescriptionController::class, 'completed'])->name('pharmacy.prescriptions.completed');
        Route::get('/pharmacy/prescriptions/history', [PharmacyPrescriptionController::class, 'history'])->name('pharmacy.prescriptions.history');
        Route::get('/pharmacy/prescriptions/{prescription}', [PharmacyPrescriptionController::class, 'show'])->name('pharmacy.prescriptions.show');
        Route::post('/pharmacy/prescriptions/{prescription}/fulfill', [PharmacyPrescriptionController::class, 'fulfill'])->name('pharmacy.prescriptions.fulfill');

        Route::get('/pharmacy/stock', [StockController::class, 'index'])->name('pharmacy.stock.index');
        Route::get('/pharmacy/stock/batches', [StockController::class, 'batches'])->name('pharmacy.stock.batches');
        Route::get('/pharmacy/stock/expired', [StockController::class, 'expired'])->name('pharmacy.stock.expired');
        Route::get('/pharmacy/stock/adjustments', [StockController::class, 'adjustments'])->name('pharmacy.stock.adjustments');
        Route::post('/pharmacy/stock/adjustments', [StockController::class, 'storeAdjustment'])->name('pharmacy.stock.adjustments.store');

        Route::get('/pharmacy/purchases/suppliers', [SupplierController::class, 'index'])->name('pharmacy.purchases.suppliers');
        Route::get('/pharmacy/purchases/suppliers/create', [SupplierController::class, 'create'])->name('pharmacy.suppliers.create');
        Route::post('/pharmacy/purchases/suppliers', [SupplierController::class, 'store'])->name('pharmacy.suppliers.store');
        Route::get('/pharmacy/purchases/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('pharmacy.suppliers.edit');
        Route::put('/pharmacy/purchases/suppliers/{supplier}', [SupplierController::class, 'update'])->name('pharmacy.suppliers.update');
        Route::delete('/pharmacy/purchases/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('pharmacy.suppliers.destroy');

        Route::get('/pharmacy/purchases/orders', [PurchaseOrderController::class, 'index'])->name('pharmacy.purchases.orders');
        Route::get('/pharmacy/purchases/orders/create', [PurchaseOrderController::class, 'create'])->name('pharmacy.orders.create');
        Route::post('/pharmacy/purchases/orders', [PurchaseOrderController::class, 'store'])->name('pharmacy.orders.store');
        Route::get('/pharmacy/purchases/orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('pharmacy.orders.show');
        Route::post('/pharmacy/purchases/orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('pharmacy.orders.submit');
        Route::get('/pharmacy/purchases/orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveForm'])->name('pharmacy.orders.receive');
        Route::post('/pharmacy/purchases/orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('pharmacy.orders.receive.store');
        Route::get('/pharmacy/purchases/receipts', [PurchaseOrderController::class, 'receipts'])->name('pharmacy.purchases.receipts');

        Route::get('/pharmacy/transactions', [PharmacyReportController::class, 'transactions'])->name('pharmacy.transactions');
        Route::get('/pharmacy/reports/stock', [PharmacyReportController::class, 'stock'])->name('pharmacy.reports.stock');
        Route::get('/pharmacy/reports/outgoing', [PharmacyReportController::class, 'outgoing'])->name('pharmacy.reports.outgoing');
        Route::get('/pharmacy/reports/incoming', [PharmacyReportController::class, 'incoming'])->name('pharmacy.reports.incoming');
        Route::get('/pharmacy/reports/expired', [PharmacyReportController::class, 'expired'])->name('pharmacy.reports.expired');
        Route::get('/reports/pharmacy', [PharmacyReportController::class, 'landing'])->name('reports.pharmacy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/visits', [ReportController::class, 'visits'])->name('reports.visits');
        Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
        Route::get('/reports/procedures', [ReportController::class, 'procedures'])->name('reports.procedures');
        Route::get('/reports/patients', [ReportController::class, 'patients'])->name('reports.patients');
        Route::get('/reports/personal', [ReportController::class, 'personal'])->name('reports.personal');
        Route::get('/reports/operational', [ReportController::class, 'operational'])->name('reports.operational');
        Route::get('/reports/medical', [ReportController::class, 'medical'])->name('reports.medical');
        Route::get('/reports/finance', [ReportController::class, 'finance'])->name('reports.finance');

        Route::get('/billing', [BillingDashboardController::class, 'index'])->name('billing.index');
        Route::get('/billing/invoices', [InvoiceController::class, 'index'])->name('billing.invoices');
        Route::get('/billing/invoices/today', [InvoiceController::class, 'today'])->name('billing.invoices.today');
        Route::get('/billing/history', [InvoiceController::class, 'history'])->name('billing.history');
        Route::post('/billing/invoices/generate', [InvoiceController::class, 'generate'])->name('billing.invoices.generate');
        Route::get('/billing/invoices/{invoice}', [InvoiceController::class, 'show'])->name('billing.invoices.show');
        Route::post('/billing/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('billing.invoices.pay');
        Route::post('/billing/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('billing.invoices.void');
        Route::get('/billing/payments', [PaymentController::class, 'index'])->name('billing.payments');
        Route::post('/billing/payments', [PaymentController::class, 'store'])->name('billing.payments.store');
        Route::get('/billing/receivables', [PaymentController::class, 'receivables'])->name('billing.receivables');

        Route::get('/cashier/shifts/open', [CashShiftController::class, 'openForm'])->name('cashier.shifts.open');
        Route::post('/cashier/shifts/open', [CashShiftController::class, 'open'])->name('cashier.shifts.open.store');
        Route::get('/cashier/shifts/transactions', [CashShiftController::class, 'transactions'])->name('cashier.shifts.transactions');
        Route::get('/cashier/shifts/close', [CashShiftController::class, 'closeForm'])->name('cashier.shifts.close');
        Route::post('/cashier/shifts/close', [CashShiftController::class, 'close'])->name('cashier.shifts.close.store');
        Route::get('/cashier/reports', [CashShiftController::class, 'reports'])->name('cashier.reports');

        Route::get('/finance', [FinanceDashboardController::class, 'index'])->name('finance.index');
        Route::get('/finance/revenue/daily', [RevenueController::class, 'daily'])->name('finance.revenue.daily');
        Route::get('/finance/revenue/monthly', [RevenueController::class, 'monthly'])->name('finance.revenue.monthly');
        Route::get('/finance/revenue/doctors', [RevenueController::class, 'doctors'])->name('finance.revenue.doctors');
        Route::get('/finance/expenses', [ExpenseController::class, 'index'])->name('finance.expenses');
        Route::post('/finance/expenses', [ExpenseController::class, 'store'])->name('finance.expenses.store');
        Route::get('/finance/expenses/categories', [ExpenseCategoryController::class, 'index'])->name('finance.expenses.categories');
        Route::post('/finance/expenses/categories', [ExpenseCategoryController::class, 'store'])->name('finance.expenses.categories.store');
        Route::put('/finance/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('finance.expenses.categories.update');
        Route::delete('/finance/expenses/categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('finance.expenses.categories.destroy');
        Route::get('/finance/expenses/suppliers', [ExpenseController::class, 'suppliers'])->name('finance.expenses.suppliers');
        Route::get('/finance/receivables', [ReceivableController::class, 'index'])->name('finance.receivables');
        Route::get('/finance/payables', [PayableController::class, 'index'])->name('finance.payables');
        Route::post('/finance/payables/{purchaseOrder}/pay', [PayableController::class, 'pay'])->name('finance.payables.pay');
        Route::get('/finance/cash-bank', [CashBankController::class, 'index'])->name('finance.cash-bank');
        Route::get('/finance/reports/revenue', [FinanceReportController::class, 'revenue'])->name('finance.reports.revenue');
        Route::get('/finance/reports/expenses', [FinanceReportController::class, 'expenses'])->name('finance.reports.expenses');
        Route::get('/finance/reports/cashflow', [FinanceReportController::class, 'cashflow'])->name('finance.reports.cashflow');
        Route::get('/finance/reports/receivables', [FinanceReportController::class, 'receivables'])->name('finance.reports.receivables');
        Route::get('/finance/reports/profit-loss', [FinanceReportController::class, 'profitLoss'])->name('finance.reports.profit-loss');

        Route::get('/settings/clinic', [ClinicSettingController::class, 'index'])
            ->middleware('permission:setting.view')
            ->name('settings.clinic');
        Route::put('/settings/clinic', [ClinicSettingController::class, 'update'])
            ->middleware('permission:setting.manage')
            ->name('settings.clinic.update');

        Route::get('/integrations', [IntegrationController::class, 'index'])
            ->name('integrations.index');
        Route::get('/integrations/satusehat', [IntegrationController::class, 'satusehat'])
            ->middleware('permission:satusehat.view')
            ->name('integrations.satusehat');
        Route::put('/integrations/satusehat', [IntegrationController::class, 'updateSatuSehat'])
            ->middleware('permission:integration.manage')
            ->name('integrations.satusehat.update');
        Route::post('/integrations/satusehat', [IntegrationController::class, 'sendSatuSehat'])
            ->middleware('permission:satusehat.view')
            ->name('integrations.satusehat.send');
        Route::post('/visits/{visit}/satusehat', [IntegrationController::class, 'sendSatuSehat'])
            ->middleware('permission:satusehat.view')
            ->name('care.satusehat.send');
        Route::get('/integrations/bpjs', [IntegrationController::class, 'bpjs'])
            ->middleware('permission:bpjs.view')
            ->name('integrations.bpjs');
        Route::put('/integrations/bpjs', [IntegrationController::class, 'updateBpjs'])
            ->middleware('permission:integration.manage')
            ->name('integrations.bpjs.update');
        Route::post('/integrations/bpjs', [IntegrationController::class, 'checkBpjs'])
            ->middleware('permission:bpjs.view')
            ->name('integrations.bpjs.check');
        Route::post('/registration/patients/{patient}/bpjs', [IntegrationController::class, 'checkBpjs'])
            ->middleware('permission:bpjs.view')
            ->name('registration.patients.bpjs');
        Route::post('/integrations/claims', [IntegrationController::class, 'storeClaim'])
            ->middleware('permission:integration.manage')
            ->name('integrations.claims.store');
        Route::get('/integrations/claims/{insuranceClaim}/download', [IntegrationController::class, 'downloadClaim'])
            ->middleware('permission:integration.view')
            ->name('integrations.claims.download');
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

        Route::get('packages', [PackageController::class, 'index'])->middleware('permission:package.view')->name('packages.index');
        Route::post('packages', [PackageController::class, 'store'])->middleware('permission:package.manage')->name('packages.store');
        Route::put('packages/{saasPackage}', [PackageController::class, 'update'])->middleware('permission:package.manage')->name('packages.update');
        Route::delete('packages/{saasPackage}', [PackageController::class, 'destroy'])->middleware('permission:package.manage')->name('packages.destroy');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->middleware('permission:subscription.view')->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->middleware('permission:subscription.manage')->name('subscriptions.store');

        Route::get('invoices', [SaasInvoiceController::class, 'index'])->middleware('permission:invoice.view')->name('invoices.index');
        Route::post('invoices/{saasInvoice}/pay', [SaasInvoiceController::class, 'pay'])->middleware('permission:subscription.manage')->name('invoices.pay');

        Route::get('system/integrations', [PlatformOpsController::class, 'integrations'])->middleware('permission:saas.integration.view')->name('system.integrations');
        Route::get('system/audit', [PlatformOpsController::class, 'audit'])->middleware('permission:saas.audit.view')->name('system.audit');
        Route::get('users/activity', [PlatformOpsController::class, 'userActivity'])->middleware('permission:saas.activity.view')->name('users.activity');
    });

    require __DIR__.'/modules.php';
});
