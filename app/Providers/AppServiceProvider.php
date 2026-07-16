<?php

namespace App\Providers;

use App\Models\{
    Admission, ClinicalNote, ClinicalOrder, DispensingRecord, Encounter, Invoice,
    LabOrder, LabResult, Patient, PatientAllergy, Payment, PharmacyStock,
    Prescription, Referral, User, VitalSign
};
use App\Observers\AuditLogObserver;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Disables Laravel's default { "data": [...] } wrapping on every
        // API Resource / Resource::collection() response. The frontend was
        // built expecting bare arrays/objects (res.data.map(...) directly),
        // not the wrapped envelope — without this line, EVERY Phase 3
        // endpoint using ::collection() breaks the same way AdminAudit just did.
        JsonResource::withoutWrapping();

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
            ClinicalNote::class,
            ClinicalOrder::class,
            Referral::class,
        ];

        foreach ($auditedModels as $model) {
            $model::observe(AuditLogObserver::class);
        }
    }
}