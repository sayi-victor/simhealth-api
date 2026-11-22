<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ClinicalModulesController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DrugController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\FormDefinitionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientHistoryController;
use App\Http\Controllers\Api\PatientIssueController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'data' => [
        'status' => 'ok',
        'service' => 'hms-api',
    ],
]));

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/facilities', [FacilityController::class, 'index'])
        ->middleware('permission:facilities.view');
    Route::get('/facilities/{facility}', [FacilityController::class, 'show'])
        ->middleware('permission:facilities.view');

    // Module 1: Patients
    Route::get('/patients', [PatientController::class, 'index'])
        ->middleware('permission:patients.view');
    Route::post('/patients', [PatientController::class, 'store'])
        ->middleware('permission:patients.create');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])
        ->middleware('permission:patients.view');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])
        ->middleware('permission:patients.update');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])
        ->middleware('permission:patients.update');
    Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])
        ->middleware('permission:patients.archive');
    Route::post('/patients/{patientId}/restore', [PatientController::class, 'restore'])
        ->middleware('permission:patients.archive');

    Route::post('/patients/{patient}/issues', [PatientIssueController::class, 'store'])
        ->middleware('permission:patients.update');
    Route::delete('/patients/{patient}/issues/{issue}', [PatientIssueController::class, 'destroy'])
        ->middleware('permission:patients.update');
    Route::put('/patients/{patient}/history', [PatientHistoryController::class, 'update'])
        ->middleware('permission:patients.update');

    // Module 2: Encounters + LBF Forms Engine
    Route::get('/form-definitions', [FormDefinitionController::class, 'index'])
        ->middleware('permission:encounters.view');
    Route::get('/form-definitions/{formDefinition}', [FormDefinitionController::class, 'show'])
        ->middleware('permission:encounters.view');

    Route::get('/encounters', [EncounterController::class, 'index'])
        ->middleware('permission:encounters.view');
    Route::post('/encounters', [EncounterController::class, 'store'])
        ->middleware('permission:encounters.create');
    Route::get('/encounters/{encounter}', [EncounterController::class, 'show'])
        ->middleware('permission:encounters.view');
    Route::put('/encounters/{encounter}', [EncounterController::class, 'update'])
        ->middleware('permission:encounters.update');
    Route::post('/encounters/{encounter}/sign', [EncounterController::class, 'sign'])
        ->middleware('permission:encounters.sign');
    Route::post('/encounters/{encounter}/vitals', [EncounterController::class, 'storeVitals'])
        ->middleware('permission:encounters.update');
    Route::post('/encounters/{encounter}/notes', [EncounterController::class, 'storeNote'])
        ->middleware('permission:encounters.update');
    Route::post('/encounters/{encounter}/forms', [EncounterController::class, 'storeForm'])
        ->middleware('permission:encounters.update');

    // Module 3: Calendar / Appointments
    Route::get('/appointment-categories', [AppointmentController::class, 'categories'])
        ->middleware('permission:calendar.view');
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->middleware('permission:calendar.view');
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('permission:calendar.manage');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
        ->middleware('permission:calendar.view');
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])
        ->middleware('permission:calendar.manage');
    Route::post('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
        ->middleware('permission:calendar.manage');
    Route::post('/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])
        ->middleware('permission:calendar.manage');
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
        ->middleware('permission:calendar.manage');

    // Module 4: Prescriptions & Pharmacy
    Route::get('/drugs', [DrugController::class, 'index'])
        ->middleware('permission:prescriptions.view');
    Route::post('/drugs', [DrugController::class, 'store'])
        ->middleware('permission:inventory.manage');
    Route::get('/drugs/{drug}', [DrugController::class, 'show'])
        ->middleware('permission:prescriptions.view');
    Route::post('/drugs/{drug}/lots', [DrugController::class, 'storeLot'])
        ->middleware('permission:inventory.manage');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])
        ->middleware('permission:prescriptions.view');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])
        ->middleware('permission:prescriptions.create');
    Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])
        ->middleware('permission:prescriptions.view');
    Route::post('/prescriptions/{prescription}/dispense', [PrescriptionController::class, 'dispense'])
        ->middleware('permission:pharmacy.dispense');
    Route::post('/prescriptions/{prescription}/cancel', [PrescriptionController::class, 'cancel'])
        ->middleware('permission:prescriptions.create');

    // Module 5: Orders (Laboratory, Imaging & Procedures)
    Route::get('/order-catalog', [OrderController::class, 'catalog'])
        ->middleware('permission:orders.view');
    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('permission:orders.view');
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:orders.create');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('permission:orders.view');
    Route::post('/orders/{order}/collect', [OrderController::class, 'collect'])
        ->middleware('permission:orders.result');
    Route::post('/orders/{order}/results', [OrderController::class, 'storeResult'])
        ->middleware('permission:orders.result');
    Route::post('/orders/{order}/review', [OrderController::class, 'review'])
        ->middleware('permission:orders.create');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->middleware('permission:orders.create');

    // Documents & Attachments (patients, encounters, clinical orders)
    Route::get('/document-categories', [DocumentController::class, 'categories'])
        ->middleware('permission:documents.view');
    Route::get('/patients/{patient}/documents', [DocumentController::class, 'indexForPatient'])
        ->middleware('permission:documents.view');
    Route::post('/patients/{patient}/documents', [DocumentController::class, 'storeForPatient'])
        ->middleware('permission:documents.upload');
    Route::get('/encounters/{encounter}/documents', [DocumentController::class, 'indexForEncounter'])
        ->middleware('permission:documents.view');
    Route::post('/encounters/{encounter}/documents', [DocumentController::class, 'storeForEncounter'])
        ->middleware('permission:documents.upload');
    Route::get('/orders/{order}/documents', [DocumentController::class, 'indexForOrder'])
        ->middleware('permission:documents.view');
    Route::post('/orders/{order}/documents', [DocumentController::class, 'storeForOrder'])
        ->middleware('permission:documents.upload');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])
        ->middleware('permission:documents.view');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('permission:documents.view');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
        ->middleware('permission:documents.delete');

    // Module 7: Clinical Billing & Claims
    Route::get('/invoices', [BillingController::class, 'indexInvoices'])
        ->middleware('permission:billing.view');
    Route::post('/invoices', [BillingController::class, 'storeInvoice'])
        ->middleware('permission:billing.manage');
    Route::get('/invoices/{invoice}', [BillingController::class, 'showInvoice'])
        ->middleware('permission:billing.view');
    Route::post('/invoices/{invoice}/payments', [BillingController::class, 'storePayment'])
        ->middleware('permission:billing.manage');
    Route::post('/invoices/{invoice}/void', [BillingController::class, 'voidInvoice'])
        ->middleware('permission:billing.manage');

    Route::get('/claims', [BillingController::class, 'indexClaims'])
        ->middleware('permission:billing.view');
    Route::post('/claims', [BillingController::class, 'storeClaim'])
        ->middleware('permission:claims.manage');
    Route::post('/claims/{claim}/adjudicate', [BillingController::class, 'adjudicateClaim'])
        ->middleware('permission:claims.manage');

    // Module 8: Inventory
    Route::get('/warehouses', [InventoryController::class, 'indexWarehouses'])
        ->middleware('permission:inventory.view');
    Route::post('/warehouses', [InventoryController::class, 'storeWarehouse'])
        ->middleware('permission:inventory.manage');
    Route::get('/inventory-items', [InventoryController::class, 'indexItems'])
        ->middleware('permission:inventory.view');
    Route::post('/inventory-items', [InventoryController::class, 'storeItem'])
        ->middleware('permission:inventory.manage');
    Route::get('/inventory-items/{item}', [InventoryController::class, 'showItem'])
        ->middleware('permission:inventory.view');
    Route::post('/inventory-items/{item}/movements', [InventoryController::class, 'storeMovement'])
        ->middleware('permission:inventory.manage');

    // Module 6: Clinical Modules (Inpatient, Surgery, Vaccination)
    Route::get('/wards', [ClinicalModulesController::class, 'indexWards'])
        ->middleware('permission:encounters.view');
    Route::get('/admissions', [ClinicalModulesController::class, 'indexAdmissions'])
        ->middleware('permission:encounters.view');
    Route::post('/admissions', [ClinicalModulesController::class, 'storeAdmission'])
        ->middleware('permission:encounters.create');
    Route::post('/admissions/{admission}/transfer', [ClinicalModulesController::class, 'transferAdmission'])
        ->middleware('permission:encounters.update');
    Route::post('/admissions/{admission}/discharge', [ClinicalModulesController::class, 'dischargeAdmission'])
        ->middleware('permission:encounters.update');

    Route::get('/surgeries', [ClinicalModulesController::class, 'indexSurgeries'])
        ->middleware('permission:encounters.view');
    Route::post('/surgeries', [ClinicalModulesController::class, 'storeSurgery'])
        ->middleware('permission:encounters.create');
    Route::post('/surgeries/{surgery}/complete', [ClinicalModulesController::class, 'completeSurgery'])
        ->middleware('permission:encounters.update');

    Route::get('/immunizations', [ClinicalModulesController::class, 'indexImmunizations'])
        ->middleware('permission:encounters.view');
    Route::post('/immunizations', [ClinicalModulesController::class, 'storeImmunization'])
        ->middleware('permission:encounters.create');

    // Module 9: Reports
    Route::get('/reports/overview', [ReportController::class, 'overview'])
        ->middleware('permission:reports.view');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])
        ->middleware('permission:reports.view');
    Route::get('/reports/inventory-alerts', [ReportController::class, 'inventoryAlerts'])
        ->middleware('permission:reports.view');

    // Module 10: Admin (Users, Roles, Facilities, Audit Logs)
    Route::get('/admin/users', [AdminController::class, 'indexUsers'])
        ->middleware('permission:users.view');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])
        ->middleware('permission:users.manage');
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])
        ->middleware('permission:users.manage');
    Route::get('/admin/roles', [AdminController::class, 'indexRoles'])
        ->middleware('permission:users.view');
    Route::post('/facilities', [AdminController::class, 'storeFacility'])
        ->middleware('permission:facilities.manage');
    Route::put('/facilities/{facility}', [AdminController::class, 'updateFacility'])
        ->middleware('permission:facilities.manage');
    Route::get('/admin/audit-logs', [AdminController::class, 'indexAuditLogs'])
        ->middleware('permission:audit.view');
});
