<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\FluidBalance;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Vital;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class WardController extends Controller
{
    private const WARDS = ['Male General', 'Female General', 'Pediatric', 'Maternity', 'ICU/HDU', 'Surgical', 'Isolation'];

    /**
     * Wards restricted to one sex. Wards not listed here (Pediatric,
     * ICU/HDU, Surgical, Isolation) are treated as mixed/unrestricted.
     */
    private const SEX_RESTRICTED_WARDS = [
        'Male General' => 'male',
        'Female General' => 'female',
        'Maternity' => 'female',
    ];

    public function listWards()
    {
        return response()->json(self::WARDS);
    }

    /**
     * Returns a message if placing this patient in $ward would violate a
     * sex-restricted ward, or null if the placement is fine. We only block
     * on a known mismatch — an unknown/unrecorded patient sex is allowed
     * through rather than blocking care, since the ward assignment can
     * always be corrected later.
     */
    private function wardSexConflict(?Patient $patient, ?string $ward): ?string
    {
        if (! $ward || ! isset(self::SEX_RESTRICTED_WARDS[$ward])) {
            return null;
        }

        $requiredSex = self::SEX_RESTRICTED_WARDS[$ward];

        if (! $patient || ! $patient->sex || $patient->sex === $requiredSex) {
            return null;
        }

        return "{$ward} is a {$requiredSex}-only ward, but this patient is recorded as {$patient->sex}. Choose a different ward, or update the patient's sex if it was recorded incorrectly.";
    }

    /**
     * GET /api/wards/occupancy
     * Excludes ICU/HDU — that ward has its own dedicated dashboard.
     */
    public function occupancy()
    {
        $admitted = Encounter::where('stage', 'admitted')->where('ward', '!=', 'ICU/HDU')->get();

        $byWard = $admitted->groupBy(fn ($e) => $e->ward ?: 'Unassigned');

        $result = $byWard->map(function ($encounters, $ward) {
            $beds = $encounters->map(function (Encounter $e) {
                $patient = Patient::find($e->patient_id);

                return [
                    'encounter_id' => $e->id,
                    'bed' => $e->bed,
                    'patient_name' => $patient ? "{$patient->given_name} {$patient->family_name}" : null,
                    'patient_uid' => $patient?->patient_uid,
                    'mrn' => $e->mrn,
                    'admission_diagnosis' => $e->admission_diagnosis,
                ];
            })->values();

            return ['ward' => $ward, 'occupied_beds' => $encounters->count(), 'patients' => $beds];
        })->values();

        return response()->json($result);
    }

    /**
     * POST /api/wards/admit
     * Roles: doctor, nurse, admin
     */
    public function admit(Request $request)
    {
        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $patient = Patient::find($encounter->patient_id);

        if ($conflict = $this->wardSexConflict($patient, $request->input('ward'))) {
            return response()->json(['error' => 'ward_sex_mismatch', 'message' => $conflict], 422);
        }

        $encounter->update([
            'stage' => 'admitted',
            'visit_type' => 'inpatient',
            'ward' => $request->input('ward'),
            'bed' => $request->input('bed'),
            'admission_diagnosis' => $request->input('admission_diagnosis'),
            'current_department' => 'ward',
        ]);

        AuditLogger::log(
            $request->user(), 'admit_patient', 'encounter', $encounter->id,
            "ward={$request->input('ward')} bed={$request->input('bed')}"
        );

        return response()->json($encounter);
    }

    /**
     * POST /api/wards/transfer
     * Roles: doctor, nurse, admin
     */
    public function transfer(Request $request)
    {
        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $fromWard = $encounter->ward;
        $targetWard = $request->input('ward', $encounter->ward);

        if ($conflict = $this->wardSexConflict(Patient::find($encounter->patient_id), $targetWard)) {
            return response()->json(['error' => 'ward_sex_mismatch', 'message' => $conflict], 422);
        }

        $encounter->update([
            'ward' => $targetWard,
            'bed' => $request->input('bed', $encounter->bed),
        ]);

        AuditLogger::log(
            $request->user(), 'transfer_patient', 'encounter', $encounter->id,
            "from={$fromWard} to={$targetWard}"
        );

        return response()->json($encounter);
    }

    /**
     * GET /api/wards/patient/{encounter}
     * Full inpatient chart — everything a ward round needs in one call.
     */
    public function patientDetail(Encounter $encounter)
    {
        $patient = Patient::find($encounter->patient_id);

        $d = $encounter->toArray();
        $d['patient'] = $patient?->toArray();
        $d['referral'] = in_array($encounter->stage, Encounter::CLOSED_STAGES, true) ? null : $encounter->activeReferralSummary();
        $d['vitals'] = Vital::where('encounter_id', $encounter->id)->orderBy('created_at')->get();
        $d['fluid_balance'] = FluidBalance::where('encounter_id', $encounter->id)->orderBy('recorded_at')->get();
        $d['notes'] = ClinicalNote::where('encounter_id', $encounter->id)->latest()->get();

        $prescriptions = Prescription::where('encounter_id', $encounter->id)->latest()->get();
        $d['prescriptions'] = $prescriptions->map(function (Prescription $p) {
            $pd = $p->toArray();
            $administrations = MedicationAdministration::where('prescription_id', $p->id)->latest('administered_at')->get();
            $pd['administrations'] = $administrations->map(function ($a) {
                $ad = $a->toArray();
                $nurse = User::find($a->administered_by);
                $ad['administered_by_name'] = $nurse?->full_name;

                return $ad;
            });

            return $pd;
        });

        return response()->json($d);
    }

    /**
     * GET /api/wards/fluid-balance/{encounter}
     */
    public function listFluidBalance(Encounter $encounter)
    {
        $entries = FluidBalance::where('encounter_id', $encounter->id)->orderBy('recorded_at')->get();
        $intakeTotal = $entries->where('direction', 'intake')->sum('volume_ml');
        $outputTotal = $entries->where('direction', 'output')->sum('volume_ml');

        return response()->json([
            'entries' => $entries,
            'intake_total_ml' => $intakeTotal,
            'output_total_ml' => $outputTotal,
            'balance_ml' => $intakeTotal - $outputTotal,
        ]);
    }

    /**
     * POST /api/wards/fluid-balance
     * Roles: nurse, doctor, admin
     */
    public function storeFluidBalance(Request $request)
    {
        if (! $request->filled('encounter_id') || ! $request->filled('direction') || ! $request->has('volume_ml')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id, direction, and volume_ml are required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));

        $entry = FluidBalance::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'direction' => $request->input('direction'),
            'category' => $request->input('category'),
            'volume_ml' => $request->input('volume_ml'),
            'notes' => $request->input('notes'),
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log(
            $request->user(), 'record_fluid_balance', 'encounter', $encounter->id,
            "{$request->input('direction')} {$request->input('volume_ml')}ml"
        );

        return response()->json($entry, 201);
    }
}
