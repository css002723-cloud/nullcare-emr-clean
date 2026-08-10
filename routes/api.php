<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ClinicalNoteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DialysisController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\ICUController;
use App\Http\Controllers\Api\ImagingController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ResearchController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalController;
use App\Http\Controllers\Api\WardController;
use Illuminate\Support\Facades\Route;

// --- Public ---
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/login', [AuthController::class, 'login']);

// --- Authenticated (any active user) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password-self', [AuthController::class, 'changePassword']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/verify-password', [AuthController::class, 'verifyPassword']);
    Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword']);

    // ---- Appointments ----
    Route::get('/appointments/doctors', [AppointmentController::class, 'doctors']);
    Route::middleware('role:reception,nurse,doctor,admin')->group(function () {
        Route::get('/appointments', [AppointmentController::class, 'index']);
    });
    Route::middleware('role:reception,nurse,admin')->group(function () {
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::post('/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn']);
    });
    Route::middleware('role:reception,nurse,doctor,admin')->group(function () {
        Route::put('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    });

    // ---- Patients ----
    Route::middleware('role:reception,nurse,doctor,admin')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
        Route::get('/patients/by-uid/{uid}', [PatientController::class, 'showByUid']);
        Route::get('/patients/{patient}/history', [PatientController::class, 'history']);
        Route::post('/patients/check-duplicate', [PatientController::class, 'checkDuplicate']);
    });
    Route::middleware('role:reception,nurse,admin')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });
    Route::middleware('role:reception,nurse,doctor,admin')->group(function () {
        Route::put('/patients/{patient}', [PatientController::class, 'update']);
    });
    Route::middleware('role:nurse,doctor,pharmacist,admin')->group(function () {
        Route::post('/patients/{patient}/allergies', [PatientController::class, 'storeAllergy']);
    });
    Route::middleware('role:records_officer,admin')->group(function () {
        Route::post('/patients/merge', [PatientController::class, 'merge']);
    });
    // Any authenticated user can pull a patient's own PDF record.
    Route::get('/patients/{patient}/export', [PatientController::class, 'exportPdf']);

    // ---- Encounters ----
    Route::get('/encounters', [EncounterController::class, 'index']);
    Route::get('/encounters/{encounter}', [EncounterController::class, 'show']);
    Route::get('/encounters/by-mrn/{mrn}', [EncounterController::class, 'showByMrn']);
    Route::get('/encounters/note-templates', [EncounterController::class, 'noteTemplates']);
    Route::middleware('role:reception,nurse,admin')->group(function () {
        Route::post('/encounters', [EncounterController::class, 'store']);
    });
    Route::post('/encounters/{encounter}/transition', [EncounterController::class, 'transition']);
    Route::middleware('role:doctor,nurse,reception,admin')->group(function () {
        Route::post('/encounters/{encounter}/close', [EncounterController::class, 'close']);
    });

    // ---- Clinical notes ----
    Route::get('/encounters/{encounter}/notes', [ClinicalNoteController::class, 'index']);
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/encounters/{encounter}/notes', [ClinicalNoteController::class, 'store']);
    });

    // ---- Vitals ----
    Route::get('/vitals/encounter/{encounter}', [VitalController::class, 'indexForEncounter']);
    Route::middleware('role:nurse,doctor,admin')->group(function () {
        Route::post('/vitals', [VitalController::class, 'store']);
    });

    // ---- Generic orders ----
    Route::get('/orders', [OrderController::class, 'index']);
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
    });
    Route::post('/orders/{order}/acknowledge', [OrderController::class, 'acknowledge']);
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // ---- Laboratory ----
    Route::get('/lab/catalog', [LabController::class, 'catalog']);
    Route::get('/lab/orders', [LabController::class, 'index']);
    Route::get('/lab/critical-unacknowledged', [LabController::class, 'criticalUnacknowledged']);
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/lab/orders', [LabController::class, 'store']);
    });
    Route::middleware('role:lab_tech,nurse,admin')->group(function () {
        Route::post('/lab/orders/{labOrder}/collect', [LabController::class, 'collect']);
    });
    Route::middleware('role:lab_tech,admin')->group(function () {
        Route::post('/lab/orders/{labOrder}/receive', [LabController::class, 'receive']);
        Route::post('/lab/orders/{labOrder}/result', [LabController::class, 'storeResult']);
        Route::post('/lab/results/{labResult}/verify', [LabController::class, 'verify']);
    });
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/lab/results/{labResult}/acknowledge-critical', [LabController::class, 'acknowledgeCritical']);
    });

    // ---- Imaging ----
    Route::get('/imaging/modalities', [ImagingController::class, 'modalities']);
    Route::get('/imaging/orders', [ImagingController::class, 'index']);
    Route::middleware('role:doctor,admin')->group(function () {
        Route::post('/imaging/orders', [ImagingController::class, 'store']);
        Route::post('/imaging/reports/{imagingReport}/review', [ImagingController::class, 'reviewReport']);
    });
    Route::middleware('role:radiologist,admin')->group(function () {
        Route::put('/imaging/orders/{imagingOrder}/status', [ImagingController::class, 'updateStatus']);
        Route::post('/imaging/orders/{imagingOrder}/report', [ImagingController::class, 'storeReport']);
    });

    // ---- Pharmacy ----
    Route::get('/pharmacy/prescriptions', [PharmacyController::class, 'indexPrescriptions']);
    Route::get('/pharmacy/stock', [PharmacyController::class, 'stock']);
    Route::middleware('role:doctor,admin')->group(function () {
        Route::post('/pharmacy/prescriptions', [PharmacyController::class, 'storePrescription']);
    });
    Route::middleware('role:pharmacist,admin')->group(function () {
        Route::post('/pharmacy/prescriptions/{prescription}/dispense', [PharmacyController::class, 'dispense']);
        Route::post('/pharmacy/stock', [PharmacyController::class, 'upsertStock']);
    });
    Route::middleware('role:nurse,admin')->group(function () {
        Route::post('/pharmacy/prescriptions/{prescription}/administer', [PharmacyController::class, 'administer']);
    });

    // ---- Billing ----
    Route::middleware('role:billing,admin')->group(function () {
        Route::get('/billing/patients/{patient}/pending-charges', [BillingController::class, 'pendingCharges']);
        Route::get('/billing/invoices', [BillingController::class, 'index']);
        Route::post('/billing/invoices', [BillingController::class, 'store']);
        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay']);
        Route::post('/billing/invoices/{invoice}/waive', [BillingController::class, 'waive']);
        Route::get('/billing/unpaid-report', [BillingController::class, 'unpaidReport']);
    });

    // ---- Wards ----
    Route::get('/wards', [WardController::class, 'listWards']);
    Route::get('/wards/occupancy', [WardController::class, 'occupancy']);
    Route::get('/wards/patient/{encounter}', [WardController::class, 'patientDetail']);
    Route::get('/wards/fluid-balance/{encounter}', [WardController::class, 'listFluidBalance']);
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/wards/admit', [WardController::class, 'admit']);
        Route::post('/wards/transfer', [WardController::class, 'transfer']);
        Route::post('/wards/fluid-balance', [WardController::class, 'storeFluidBalance']);
    });

    // ---- Referrals / inter-department messaging ----
    Route::get('/referrals', [ReferralController::class, 'index']);
    Route::get('/referrals/inbox', [ReferralController::class, 'inbox']);
    Route::post('/referrals', [ReferralController::class, 'store']);
    Route::post('/referrals/{referral}/read', [ReferralController::class, 'markRead']);
    Route::post('/referrals/{referral}/accept', [ReferralController::class, 'accept']);
    Route::post('/referrals/{referral}/decline', [ReferralController::class, 'decline']);

    // ---- ICU ----
    Route::get('/icu/patients', [ICUController::class, 'listPatients']);
    Route::get('/icu/notes/{encounter}', [ICUController::class, 'listNotes']);
    Route::get('/icu/patient/{encounter}', [ICUController::class, 'patientDetail']);
    Route::get('/icu/critical-alerts', [ICUController::class, 'criticalAlerts']);
    Route::get('/icu/dashboard', [ICUController::class, 'dashboard']);
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/icu/admit', [ICUController::class, 'admit']);
        Route::post('/icu/notes', [ICUController::class, 'storeNote']);
    });
    Route::middleware('role:doctor,admin')->group(function () {
        Route::post('/icu/discharge', [ICUController::class, 'discharge']);
    });

    // ---- Dialysis ----
    Route::get('/dialysis/patients', [DialysisController::class, 'listPatients']);
    Route::get('/dialysis/sessions', [DialysisController::class, 'index']);
    Route::get('/dialysis/dashboard/{patient}', [DialysisController::class, 'patientDashboard']);
    Route::middleware('role:dialysis_tech,doctor,nurse,admin')->group(function () {
        Route::post('/dialysis/sessions', [DialysisController::class, 'store']);
        Route::put('/dialysis/sessions/{dialysisSession}', [DialysisController::class, 'update']);
    });

    // ---- Dashboard ----
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // ---- Sync (offline queue) ----
    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // ---- Inventory ----
    Route::get('/inventory/categories', [InventoryController::class, 'categories']);
    Route::get('/inventory/items', [InventoryController::class, 'index']);
    Route::get('/inventory/items/{inventoryItem}', [InventoryController::class, 'show']);
    Route::get('/inventory/consumption', [InventoryController::class, 'listConsumption']);
    Route::get('/inventory/alerts', [InventoryController::class, 'alerts']);
    Route::middleware('role:pharmacist,lab_tech,radiologist,nurse,dialysis_tech,admin')->group(function () {
        Route::post('/inventory/items', [InventoryController::class, 'store']);
        Route::put('/inventory/items/{inventoryItem}', [InventoryController::class, 'update']);
        Route::post('/inventory/items/{inventoryItem}/batches', [InventoryController::class, 'receiveBatch']);
        Route::post('/inventory/consume', [InventoryController::class, 'consume']);
    });

    // ---- Equipment (nested under /inventory, matching the reference's blueprint) ----
    Route::get('/inventory/equipment', [EquipmentController::class, 'index']);
    Route::get('/inventory/equipment/statuses', [EquipmentController::class, 'statuses']);
    Route::get('/inventory/equipment/dashboard', [EquipmentController::class, 'dashboard']);
    Route::get('/inventory/equipment/{equipment}/maintenance', [EquipmentController::class, 'listMaintenance']);
    Route::get('/inventory/downtime', [EquipmentController::class, 'listDowntime']);
    Route::middleware('role:pharmacist,lab_tech,radiologist,nurse,dialysis_tech,admin')->group(function () {
        Route::post('/inventory/equipment', [EquipmentController::class, 'store']);
        Route::put('/inventory/equipment/{equipment}', [EquipmentController::class, 'update']);
        Route::post('/inventory/equipment/{equipment}/maintenance', [EquipmentController::class, 'logMaintenance']);
        Route::post('/inventory/equipment/{equipment}/downtime', [EquipmentController::class, 'reportDowntime']);
        Route::post('/inventory/downtime/{downtimeReport}/resolve', [EquipmentController::class, 'resolveDowntime']);
    });

    // ---- Admin: users, audit, security ----
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/auth/security-alerts', [AuthController::class, 'securityAlerts']);
        Route::get('/audit', [AuditController::class, 'index']);
    });

    // ---- Research (records_officer, admin) ----
    Route::middleware('role:records_officer,admin')->group(function () {
        Route::get('/research/export', [ResearchController::class, 'export']);
        Route::get('/research/consent-summary', [ResearchController::class, 'consentSummary']);
    });
});
