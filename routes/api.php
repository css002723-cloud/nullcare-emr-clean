<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ClinicalNoteController;
use App\Http\Controllers\Api\ClinicalOrderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PharmacyController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalSignController;
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

    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // ---- Patients ----
    Route::middleware('role:reception,doctor,nurse,admin')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
        Route::post('/patients/check-duplicate', [PatientController::class, 'checkDuplicate']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
        Route::get('/patients/{patient}/history', [PatientController::class, 'history']);
    });
    Route::middleware('role:reception,admin')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/patients/{patient}/allergies', [PatientController::class, 'storeAllergy']);
    });

    // ---- Encounters ----
    Route::middleware('role:reception,doctor,nurse,admin')->group(function () {
        Route::get('/encounters', [EncounterController::class, 'index']);
        Route::post('/encounters', [EncounterController::class, 'store']);
        Route::get('/encounters/{encounter}', [EncounterController::class, 'show']);
    });
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/encounters/{encounter}/close', [EncounterController::class, 'close']);
    });

    // ---- Vitals ----
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::get('/vitals/encounter/{encounter}', [VitalSignController::class, 'indexForEncounter']);
        Route::post('/vitals', [VitalSignController::class, 'store']);
    });

    // ---- Clinical notes ----
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::get('/encounters/{encounter}/notes', [ClinicalNoteController::class, 'index']);
        Route::post('/encounters/{encounter}/notes', [ClinicalNoteController::class, 'store']);
    });

    // ---- Generic clinical orders ----
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::get('/orders', [ClinicalOrderController::class, 'index']);
    });
    Route::middleware('role:doctor,admin')->group(function () {
        Route::post('/orders', [ClinicalOrderController::class, 'store']);
    });

    // ---- Referrals ----
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/referrals', [ReferralController::class, 'store']);
    });

    // ---- Laboratory ----
    Route::middleware('role:doctor,nurse,lab_tech,admin')->group(function () {
        Route::get('/lab/catalog', [LabController::class, 'catalog']);
        Route::get('/lab/orders', [LabController::class, 'index']);
        Route::get('/lab/critical-unacknowledged', [LabController::class, 'criticalUnacknowledged']);
    });
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/lab/orders', [LabController::class, 'store']);
    });
    Route::middleware('role:lab_tech,admin')->group(function () {
        Route::post('/lab/orders/{labOrder}/collect', [LabController::class, 'collect']);
        Route::post('/lab/orders/{labOrder}/receive', [LabController::class, 'receive']);
        Route::post('/lab/orders/{labOrder}/result', [LabController::class, 'storeResult']);
    });

    // ---- Pharmacy ----
    Route::middleware('role:doctor,nurse,pharmacist,admin')->group(function () {
        Route::get('/pharmacy/prescriptions', [PharmacyController::class, 'indexPrescriptions']);
        Route::get('/pharmacy/stock', [PharmacyController::class, 'stock']);
    });
    Route::middleware('role:doctor,admin')->group(function () {
        Route::post('/pharmacy/prescriptions', [PharmacyController::class, 'storePrescription']);
    });
    Route::middleware('role:pharmacist,admin')->group(function () {
        Route::post('/pharmacy/prescriptions/{prescription}/dispense', [PharmacyController::class, 'dispense']);
    });

    // ---- Billing ----
    Route::middleware('role:billing,admin')->group(function () {
        Route::get('/billing/invoices', [BillingController::class, 'index']);
        Route::post('/billing/invoices', [BillingController::class, 'store']);
        Route::post('/billing/invoices/{invoice}/pay', [BillingController::class, 'pay']);
        Route::post('/billing/invoices/{invoice}/waive', [BillingController::class, 'waive']);
        Route::get('/billing/unpaid-report', [BillingController::class, 'unpaidReport']);
    });

    // ---- Wards ----
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::get('/wards/occupancy', [WardController::class, 'occupancy']);
        Route::post('/wards/admit', [WardController::class, 'admit']);
    });

    // ---- Admin: users & audit ----
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::get('/audit', [AuditController::class, 'index']);
    });
});
