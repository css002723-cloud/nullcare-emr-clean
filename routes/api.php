<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DispensingController;
use App\Http\Controllers\Api\EncounterController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LabOrderController;
use App\Http\Controllers\Api\LabResultController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PharmacyStockController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\VitalSignController;
use Illuminate\Support\Facades\Route;

// --- Public ---
Route::post('/login', [AuthController::class, 'login']);

// Alias to match the frontend's existing AuthContext.jsx calls (/api/auth/login).
// Both paths work — keep whichever your team standardizes on later.
Route::post('/auth/login', [AuthController::class, 'login']);

// --- Authenticated (any active user) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Same aliasing as above, for logout/me.
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Patients: registration & search are typically reception, but
    // doctors/nurses also need to search/view during a visit.
    Route::middleware('role:receptionist,doctor,nurse,admin')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
        Route::get('/patients/check-duplicates', [PatientController::class, 'checkDuplicates']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);
        Route::get('/patients/{patient}/encounters', [EncounterController::class, 'index']);
    });

    // Only reception (or admin, for corrections) creates new patient records.
    Route::middleware('role:receptionist,admin')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });

    // Encounters + vitals + labs + prescriptions: clinical staff during a visit.
    Route::middleware('role:doctor,nurse,admin')->group(function () {
        Route::post('/encounters', [EncounterController::class, 'store']);
        Route::get('/encounters/{encounter}', [EncounterController::class, 'show']);
        Route::patch('/encounters/{encounter}', [EncounterController::class, 'update']);
        Route::post('/encounters/{encounter}/close', [EncounterController::class, 'close']);

        Route::get('/encounters/{encounter}/vital-signs', [VitalSignController::class, 'index']);
        Route::post('/encounters/{encounter}/vital-signs', [VitalSignController::class, 'store']);

        Route::get('/encounters/{encounter}/lab-orders', [LabOrderController::class, 'index']);
        Route::post('/encounters/{encounter}/lab-orders', [LabOrderController::class, 'store']);

        Route::post('/encounters/{encounter}/prescriptions', [PrescriptionController::class, 'store']);
        Route::get('/encounters/{encounter}/prescriptions', [PrescriptionController::class, 'index']);

        Route::post('/encounters/{encounter}/admission', [AdmissionController::class, 'store']);
        Route::patch('/admissions/{admission}/discharge', [AdmissionController::class, 'discharge']);
    });

    // Lab tech: specimen tracking + result entry.
    Route::middleware('role:lab_tech,admin')->group(function () {
        Route::patch('/lab-orders/{labOrder}/status', [LabOrderController::class, 'updateStatus']);
        Route::post('/lab-orders/{labOrder}/result', [LabResultController::class, 'store']);
        Route::patch('/lab-results/{labResult}/verify', [LabResultController::class, 'verify']);
    });

    // Pharmacist: dispensing + stock management.
    Route::middleware('role:pharmacist,admin')->group(function () {
        Route::post('/prescriptions/{prescription}/dispense', [DispensingController::class, 'store']);
        Route::get('/pharmacy-stock', [PharmacyStockController::class, 'index']);
        Route::post('/pharmacy-stock', [PharmacyStockController::class, 'store']);
        Route::patch('/pharmacy-stock/{pharmacyStock}/adjust', [PharmacyStockController::class, 'adjust']);
    });

    // Billing officer: invoices + payments.
    Route::middleware('role:billing,admin')->group(function () {
        Route::get('/patients/{patient}/invoices', [InvoiceController::class, 'index']);
        Route::post('/patients/{patient}/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
    });
});