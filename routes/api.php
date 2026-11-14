<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingInvoiceController;
use App\Http\Controllers\Api\BillingPaymentController;
use App\Http\Controllers\Api\CareController;
use App\Http\Controllers\Api\CashShiftController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MedicalLetterController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientHistoryController;
use App\Http\Controllers\Api\PharmacyLabController;
use App\Http\Controllers\Api\PharmacyOrderController;
use App\Http\Controllers\Api\PharmacyPrescriptionController;
use App\Http\Controllers\Api\PharmacyReportController;
use App\Http\Controllers\Api\PharmacyStockController;
use App\Http\Controllers\Api\PharmacySupplierController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\WilayahController;
use App\Http\Middleware\EnsureApiVisitTenant;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'store']);
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:register');

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'destroy']);

        Route::middleware('mobile.branch:optional')->group(function () {
            Route::get('me', [MeController::class, 'show']);
            Route::get('onboarding', [OnboardingController::class, 'show']);

            Route::middleware('mobile.writes')->group(function () {
                Route::post('onboarding/steps/{step}', [OnboardingController::class, 'step']);
                Route::post('onboarding/steps/{step}/skip', [OnboardingController::class, 'skip']);
                Route::post('onboarding/back', [OnboardingController::class, 'back']);
                Route::post('onboarding/complete', [OnboardingController::class, 'complete']);
            });
        });

        Route::middleware('mobile.branch')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'show']);

            Route::get('patients/search', [PatientController::class, 'search']);
            Route::get('patients', [PatientController::class, 'index']);
            Route::get('patients/{patient}', [PatientController::class, 'show']);

            Route::get('visits/availability', [VisitController::class, 'availability']);
            Route::get('visits', [VisitController::class, 'index']);
            Route::get('visits/{visit}', [VisitController::class, 'show']);

            Route::middleware(EnsureApiVisitTenant::class)->group(function () {
                Route::get('visits/{visit}/care', [CareController::class, 'show']);
                Route::get('visits/{visit}/care/diagnoses/search', [CareController::class, 'searchDiagnoses']);
                Route::get('visits/{visit}/care/procedures/icd9/search', [CareController::class, 'searchIcd9']);
                Route::get('visits/{visit}/care/procedures/tariff', [CareController::class, 'quoteProcedureTariff']);
                Route::get('visits/{visit}/care/resume/pdf', [CareController::class, 'pdfResume']);
                Route::get('visits/{visit}/care/documents/{document}/pdf', [CareController::class, 'pdfDocument']);
                Route::get('visits/{visit}/care/referrals/{referral}/pdf', [CareController::class, 'pdfReferral']);
                Route::get('visits/{visit}/care/prescriptions/pdf', [CareController::class, 'pdfPrescriptions']);
                Route::get('visits/{visit}/care/prescriptions/{prescription}/pdf', [CareController::class, 'pdfPrescription']);
                Route::get('visits/{visit}/care/instructions/pdf', [CareController::class, 'pdfInstructions']);
                Route::get('visits/{visit}/care/discharge/pdf', [CareController::class, 'pdfDischarge']);
            });

            Route::get('medical-letters', [MedicalLetterController::class, 'index']);
            Route::get('medical-letters/{letter}', [MedicalLetterController::class, 'show']);
            Route::get('medical-letters/{letter}/pdf', [MedicalLetterController::class, 'pdf']);

            Route::get('patients/{patient}/history', [PatientHistoryController::class, 'show']);
            Route::get('patients/{patient}/history/visits/{visit}', [PatientHistoryController::class, 'visit']);
            Route::get('patients/{patient}/history/resume/{document}/pdf', [PatientHistoryController::class, 'resumePdf']);
            Route::get('patients/{patient}/history/prescriptions/{prescription}/pdf', [PatientHistoryController::class, 'printPrescription']);

            Route::get('queues', [QueueController::class, 'index']);

            Route::get('pharmacy/prescriptions', [PharmacyPrescriptionController::class, 'index']);
            Route::get('pharmacy/prescriptions/{prescription}', [PharmacyPrescriptionController::class, 'show']);
            Route::get('pharmacy/prescriptions/{prescription}/labels', [PharmacyPrescriptionController::class, 'printLabels']);
            Route::get('pharmacy/prescriptions/{prescription}/items/{item}/label', [PharmacyPrescriptionController::class, 'printItemLabel']);

            Route::get('pharmacy/stock', [PharmacyStockController::class, 'index']);
            Route::get('pharmacy/stock/batches', [PharmacyStockController::class, 'batches']);
            Route::get('pharmacy/stock/expired', [PharmacyStockController::class, 'expired']);
            Route::get('pharmacy/stock/adjustments', [PharmacyStockController::class, 'adjustments']);
            Route::get('pharmacy/stock/card/{medicine}', [PharmacyStockController::class, 'cardShow']);
            Route::get('pharmacy/stock/card', [PharmacyStockController::class, 'card']);
            Route::get('pharmacy/stock/opname', [PharmacyStockController::class, 'opname']);
            Route::get('pharmacy/stock/returns', [PharmacyStockController::class, 'returns']);

            Route::get('pharmacy/lab', [PharmacyLabController::class, 'index']);
            Route::get('pharmacy/suppliers', [PharmacySupplierController::class, 'index']);
            Route::get('pharmacy/suppliers/{supplier}', [PharmacySupplierController::class, 'show']);
            Route::get('pharmacy/orders', [PharmacyOrderController::class, 'index']);
            Route::get('pharmacy/orders/{purchaseOrder}', [PharmacyOrderController::class, 'show']);
            Route::get('pharmacy/orders/{purchaseOrder}/receive', [PharmacyOrderController::class, 'receiveForm']);
            Route::get('pharmacy/receipts', [PharmacyOrderController::class, 'receipts']);
            Route::get('pharmacy/transactions', [PharmacyReportController::class, 'transactions']);
            Route::get('pharmacy/reports/stock', [PharmacyReportController::class, 'stock']);
            Route::get('pharmacy/reports/outgoing', [PharmacyReportController::class, 'outgoing']);
            Route::get('pharmacy/reports/incoming', [PharmacyReportController::class, 'incoming']);
            Route::get('pharmacy/reports/expired', [PharmacyReportController::class, 'expired']);
            Route::get('pharmacy/medicines', [PharmacyReportController::class, 'medicines']);

            Route::get('billing/invoices/search', [BillingInvoiceController::class, 'search']);
            Route::get('billing/invoices/today-patients', [BillingInvoiceController::class, 'todayPatients']);
            Route::get('billing/invoices', [BillingInvoiceController::class, 'index']);
            Route::get('billing/invoices/{invoice}', [BillingInvoiceController::class, 'show']);
            Route::get('billing/invoices/{invoice}/print', [BillingInvoiceController::class, 'print']);
            Route::get('billing/invoices/{invoice}/receipt', [BillingInvoiceController::class, 'receipt']);
            Route::get('billing/payments', [BillingPaymentController::class, 'index']);
            Route::get('billing/receivables', [BillingPaymentController::class, 'receivables']);

            Route::get('cashier/shifts/open', [CashShiftController::class, 'openForm']);
            Route::get('cashier/shifts/close', [CashShiftController::class, 'closeForm']);
            Route::get('cashier/shifts/transactions', [CashShiftController::class, 'transactions']);
            Route::get('cashier/reports', [CashShiftController::class, 'reports']);

            Route::middleware('mobile.writes')->group(function () {
                Route::post('pharmacy/prescriptions/{prescription}/fulfill', [PharmacyPrescriptionController::class, 'fulfill']);
                Route::post('pharmacy/stock/adjustments', [PharmacyStockController::class, 'storeAdjustment']);
                Route::post('pharmacy/stock/opname', [PharmacyStockController::class, 'storeOpname']);
                Route::post('pharmacy/stock/returns', [PharmacyStockController::class, 'storeReturn']);
                Route::put('pharmacy/lab/{order}', [PharmacyLabController::class, 'update']);
                Route::post('pharmacy/suppliers', [PharmacySupplierController::class, 'store']);
                Route::put('pharmacy/suppliers/{supplier}', [PharmacySupplierController::class, 'update']);
                Route::delete('pharmacy/suppliers/{supplier}', [PharmacySupplierController::class, 'destroy']);
                Route::post('pharmacy/orders', [PharmacyOrderController::class, 'store']);
                Route::post('pharmacy/orders/{purchaseOrder}/submit', [PharmacyOrderController::class, 'submit']);
                Route::post('pharmacy/orders/{purchaseOrder}/receive', [PharmacyOrderController::class, 'receive']);

                Route::post('billing/invoices/generate', [BillingInvoiceController::class, 'generate']);
                Route::post('billing/invoices/{invoice}/confirm', [BillingInvoiceController::class, 'confirm']);
                Route::post('billing/invoices/{invoice}/medicines/{prescriptionItem}/decline', [BillingInvoiceController::class, 'declineMedicine']);
                Route::post('billing/invoices/{invoice}/pay', [BillingInvoiceController::class, 'pay']);
                Route::post('billing/invoices/{invoice}/refund', [BillingInvoiceController::class, 'refund']);
                Route::post('billing/invoices/{invoice}/void', [BillingInvoiceController::class, 'void']);
                Route::post('billing/payments', [BillingPaymentController::class, 'store']);

                Route::post('cashier/shifts/open', [CashShiftController::class, 'open']);
                Route::post('cashier/shifts/close', [CashShiftController::class, 'close']);

                Route::post('patients', [PatientController::class, 'store']);
                Route::put('patients/{patient}', [PatientController::class, 'update']);
                Route::delete('patients/{patient}', [PatientController::class, 'destroy']);
                Route::post('patients/{patient}/satusehat', [PatientController::class, 'fetchIhs']);

                Route::post('visits/patient-ihs', [VisitController::class, 'patientIhs']);
                Route::post('visits', [VisitController::class, 'store']);
                Route::post('visits/{visit}/cancel', [VisitController::class, 'cancel']);

                Route::post('queues/{queue}/call', [QueueController::class, 'call']);
                Route::post('queues/{queue}/complete', [QueueController::class, 'complete']);
                Route::post('queues/{queue}/skip', [QueueController::class, 'skip']);

                Route::middleware(EnsureApiVisitTenant::class)->group(function () {
                    Route::middleware('record.mutable')->group(function () {
                        Route::put('visits/{visit}/care/record', [CareController::class, 'updateRecord']);
                        Route::put('visits/{visit}/care/examination', [CareController::class, 'updateExamination']);
                        Route::put('visits/{visit}/care/vitals', [CareController::class, 'updateVitals']);
                        Route::put('visits/{visit}/care/anamnesis', [CareController::class, 'updateAnamnesis']);
                        Route::put('visits/{visit}/care/follow-up', [CareController::class, 'updateFollowUp']);
                        Route::put('visits/{visit}/care/notes', [CareController::class, 'updateNotes']);
                        Route::put('visits/{visit}/care/tooth', [CareController::class, 'updateTooth']);
                        Route::delete('visits/{visit}/care/tooth/{toothNumber}', [CareController::class, 'destroyTooth'])
                            ->where('toothNumber', '[0-9]{2}');
                        Route::put('visits/{visit}/care/dental-exam', [CareController::class, 'updateDentalExam']);
                        Route::put('visits/{visit}/care/systemic', [CareController::class, 'updateSystemicHistory']);
                        Route::post('visits/{visit}/care/diagnoses', [CareController::class, 'storeDiagnosis']);
                        Route::put('visits/{visit}/care/diagnoses/{diagnosis}', [CareController::class, 'updateDiagnosis']);
                        Route::delete('visits/{visit}/care/diagnoses/{diagnosis}', [CareController::class, 'destroyDiagnosis']);
                        Route::post('visits/{visit}/care/procedures', [CareController::class, 'storeProcedure']);
                        Route::put('visits/{visit}/care/procedures/{procedure_record}', [CareController::class, 'updateProcedure']);
                        Route::delete('visits/{visit}/care/procedures/{procedure_record}', [CareController::class, 'destroyProcedure']);
                        Route::post('visits/{visit}/care/procedures/{procedure_record}/waiver/approve', [CareController::class, 'approveProcedureWaiver']);
                        Route::post('visits/{visit}/care/prescription-items', [CareController::class, 'storePrescriptionItem']);
                        Route::put('visits/{visit}/care/prescription-items/{prescriptionItem}', [CareController::class, 'updatePrescriptionItem']);
                        Route::delete('visits/{visit}/care/prescription-items/{prescriptionItem}', [CareController::class, 'destroyPrescriptionItem']);
                        Route::post('visits/{visit}/care/prescriptions/{prescription}/send', [CareController::class, 'sendPrescription']);
                        Route::post('visits/{visit}/care/prescription-templates', [CareController::class, 'storePrescriptionTemplate']);
                        Route::put('visits/{visit}/care/prescription-templates/{prescriptionTemplate}', [CareController::class, 'updatePrescriptionTemplate']);
                        Route::delete('visits/{visit}/care/prescription-templates/{prescriptionTemplate}', [CareController::class, 'destroyPrescriptionTemplate']);
                        Route::post('visits/{visit}/care/prescription-templates/{prescriptionTemplate}/apply', [CareController::class, 'applyPrescriptionTemplate']);
                        Route::post('visits/{visit}/care/prescriptions/{prescription}/template', [CareController::class, 'storeTemplateFromPrescription']);
                        Route::post('visits/{visit}/care/referrals', [CareController::class, 'storeReferral']);
                        Route::put('visits/{visit}/care/referrals/{referral}', [CareController::class, 'updateReferral']);
                        Route::delete('visits/{visit}/care/referrals/{referral}', [CareController::class, 'destroyReferral']);
                        Route::post('visits/{visit}/care/lab', [CareController::class, 'storeLab']);
                        Route::put('visits/{visit}/care/lab/{order}', [CareController::class, 'updateLab']);
                        Route::delete('visits/{visit}/care/lab/{order}', [CareController::class, 'destroyLab']);
                    });
                    Route::post('visits/{visit}/care/finalize', [CareController::class, 'finalizeRecord']);
                    Route::post('visits/{visit}/care/assessment/complete', [CareController::class, 'completeAssessment']);
                    Route::post('visits/{visit}/care/amendments', [CareController::class, 'storeAmendment']);
                    Route::post('visits/{visit}/care/sick-letter', [CareController::class, 'storeSickLetter']);
                    Route::post('visits/{visit}/care/complete', [CareController::class, 'complete']);
                    Route::post('visits/{visit}/medical-letters/preview', [MedicalLetterController::class, 'preview']);
                    Route::post('visits/{visit}/medical-letters/frame', [MedicalLetterController::class, 'frame']);
                    Route::post('visits/{visit}/medical-letters', [MedicalLetterController::class, 'store']);
                });

                Route::post('medical-letters/{letter}/cancel', [MedicalLetterController::class, 'cancel']);
                Route::post('medical-letters/{letter}/revise', [MedicalLetterController::class, 'revise']);
                Route::post('patients/{patient}/history/resume', [PatientHistoryController::class, 'storeResume']);
            });
            Route::get('wilayah/cities', [WilayahController::class, 'cities']);
            Route::get('wilayah/districts', [WilayahController::class, 'districts']);
            Route::get('wilayah/villages', [WilayahController::class, 'villages']);
            Route::get('wilayah/from-nik', [WilayahController::class, 'fromNik']);
        });
    });
});