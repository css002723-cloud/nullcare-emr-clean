<?php

namespace App\Providers;

use App\Models\{
    Admission, DispensingRecord, Encounter, Invoice, LabOrder, LabResult,
    Patient, PatientAllergy, Payment, PharmacyStock, Prescription, User, VitalSign
};
use App\Observers\AuditLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every model where a create/update/delete should leave an audit
        // trail per the brief's governance requirement (Section 9.2-9.4).
        // sync_queue and system_alerts are intentionally excluded — they're
        // system-generated, not user actions.
        $auditedModels = [
            Patient::class,
            PatientAllergy::class,
            Encounter::class,
            VitalSign::class,
            LabOrder::class,
            LabResult::class,
            Prescription::class,
            DispensingRecord::class,
            PharmacyStock::class,
            Invoice::class,
            Payment::class,
            Admission::class,
            User::class,
        ];

        foreach ($auditedModels as $model) {
            $model::observe(AuditLogObserver::class);
        }
    }
}