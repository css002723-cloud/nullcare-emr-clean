<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllergyResource;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\PatientResource;
use App\Models\Encounter;
use App\Models\Patient;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    /**
     * GET /api/patients?q=&status=active|completed|all
     * Ported from list_patients() in patients.py, including the
     * active/completed/all semantics: "active" = has an open visit right
     * now OR has never had a visit at all; "completed" = has at least one
     * visit and none currently open.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'active');

        $query = Patient::whereNull('merged_into_patient_id');

        if ($q) {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($like) {
                $sub->where('given_name', 'like', $like)
                    ->orWhere('family_name', 'like', $like)
                    ->orWhere('patient_uid', 'like', $like)
                    ->orWhere('national_id', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if (in_array($status, ['active', 'completed'], true)) {
            $activeIds = Encounter::whereNotIn('stage', Encounter::CLOSED_STAGES)->distinct()->pluck('patient_id');
            $anyEncounterIds = Encounter::distinct()->pluck('patient_id');

            if ($status === 'active') {
                $query->where(function ($sub) use ($activeIds, $anyEncounterIds) {
                    $sub->whereIn('id', $activeIds)->orWhereNotIn('id', $anyEncounterIds);
                });
            } else {
                $query->whereIn('id', $anyEncounterIds)->whereNotIn('id', $activeIds);
            }
        }

        $patients = $query->orderByDesc('created_at')->limit(100)->get();

        $result = $patients->map(function (Patient $p) {
            $latest = Encounter::where('patient_id', $p->id)->latest()->first();
            $d = (new PatientResource($p))->toArray(request());
            $d['latest_encounter_at'] = $latest?->created_at?->toIso8601String();
            $d['latest_encounter_number'] = $latest?->encounter_number;
            $d['latest_mrn'] = $latest?->mrn;
            $d['latest_encounter_stage'] = $latest?->stage;
            $d['has_active_encounter'] = $latest && ! in_array($latest->stage, Encounter::CLOSED_STAGES, true);

            return $d;
        });

        return response()->json($result);
    }

    /**
     * GET /api/patients/{patient}
     */
    public function show(Patient $patient)
    {
        $result = (new PatientResource($patient))->toArray(request());
        $result['allergies'] = AllergyResource::collection($patient->allergies)->toArray(request());
        $result['is_pediatric'] = $patient->isPediatric();

        return response()->json($result);
    }

    /**
     * GET /api/patients/by-uid/{uid}
     * The permanent cross-visit identifier lookup — how the frontend
     * resolves "this returning patient" without touching a visit-specific MRN.
     */
    public function showByUid(string $uid)
    {
        $patient = Patient::where('patient_uid', strtoupper(trim($uid)))->first();

        if (! $patient) {
            return response()->json(['error' => 'not_found', 'message' => "No patient found with ID {$uid}"], 404);
        }

        $result = (new PatientResource($patient))->toArray(request());
        $result['allergies'] = AllergyResource::collection($patient->allergies)->toArray(request());
        $result['is_pediatric'] = $patient->isPediatric();

        return response()->json($result);
    }

    /**
     * POST /api/patients/check-duplicate
     * Matches on national_id OR (given_name AND family_name), exactly as
     * the reference does — not a staged DoB-first algorithm (that was my
     * own earlier design before this merge; the reference's simpler
     * approach is now the source of truth).
     */
    public function checkDuplicate(Request $request)
    {
        $matches = collect();

        if ($request->filled('national_id')) {
            $matches = $matches->merge(
                Patient::whereNull('merged_into_patient_id')->where('national_id', $request->input('national_id'))->get()
            );
        }

        if ($request->filled('given_name') && $request->filled('family_name')) {
            $nameMatches = Patient::whereNull('merged_into_patient_id')
                ->where('given_name', 'like', $request->input('given_name'))
                ->where('family_name', 'like', $request->input('family_name'))
                ->get();

            $matches = $matches->merge($nameMatches)->unique('id');
        }

        return PatientResource::collection($matches->take(10));
    }

    /**
     * POST /api/patients
     * Roles: reception, nurse, admin
     */
    public function store(Request $request)
    {
        if (! $request->filled('given_name') || ! $request->filled('family_name')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'Missing: given_name, family_name'], 400);
        }

        $dob = null;
        if ($request->filled('date_of_birth')) {
            try {
                $dob = \Carbon\Carbon::createFromFormat('Y-m-d', $request->input('date_of_birth'))->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'invalid_date', 'message' => 'date_of_birth must be YYYY-MM-DD'], 400);
            }
        }

        do {
            $patientUid = IdGenerator::patientUid();
        } while (Patient::where('patient_uid', $patientUid)->exists());

        $user = $request->user();

        $patient = DB::transaction(function () use ($request, $dob, $patientUid, $user) {
            $patient = Patient::create([
                'patient_uid' => $patientUid,
                'national_id' => $request->input('national_id'),
                'given_name' => trim($request->input('given_name')),
                'family_name' => trim($request->input('family_name')),
                'sex' => $request->input('sex'),
                'date_of_birth' => $dob,
                'estimated_age' => $request->input('estimated_age'),
                'phone' => $request->input('phone'),
                'village' => $request->input('village'),
                'traditional_authority' => $request->input('traditional_authority'),
                'district' => $request->input('district'),
                'region' => $request->input('region'),
                'occupation' => $request->input('occupation'),
                'guardian_name' => $request->input('guardian_name'),
                'guardian_relationship' => $request->input('guardian_relationship'),
                'guardian_phone' => $request->input('guardian_phone'),
                'patient_category' => $request->input('patient_category', 'outpatient'),
                'consent_care' => $request->input('consent_care', true),
                'consent_research' => $request->input('consent_research', false),
                'consent_teaching' => $request->input('consent_teaching', false),
                'registered_by' => $user->id,
                'client_uuid' => $request->input('client_uuid'),
            ]);

            foreach ($request->input('allergies', []) as $allergy) {
                $patient->allergies()->create([
                    'substance' => $allergy['substance'] ?? null,
                    'reaction' => $allergy['reaction'] ?? null,
                    'severity' => $allergy['severity'] ?? null,
                    'recorded_by' => $user->id,
                ]);
            }

            return $patient;
        });

        AuditLogger::log($user, 'register_patient', 'patient', $patient->id, "uid={$patientUid}");

        return response()->json(new PatientResource($patient), 201);
    }

    /**
     * PUT /api/patients/{patient}
     * Roles: reception, nurse, doctor, admin
     */
    public function update(Request $request, Patient $patient)
    {
        $editable = [
            'phone', 'village', 'traditional_authority', 'district', 'region', 'occupation',
            'guardian_name', 'guardian_relationship', 'guardian_phone', 'patient_category',
            'consent_care', 'consent_research', 'consent_teaching',
        ];

        foreach ($editable as $field) {
            if ($request->has($field)) {
                $patient->{$field} = $request->input($field);
            }
        }

        $patient->save();

        AuditLogger::log($request->user(), 'update_patient', 'patient', $patient->id);

        return new PatientResource($patient);
    }

    /**
     * POST /api/patients/{patient}/allergies
     * Roles: nurse, doctor, pharmacist, admin
     */
    public function storeAllergy(Request $request, Patient $patient)
    {
        if (! $request->filled('substance')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'substance is required'], 400);
        }

        $allergy = $patient->allergies()->create([
            'substance' => $request->input('substance'),
            'reaction' => $request->input('reaction'),
            'severity' => $request->input('severity'),
            'recorded_by' => $request->user()->id,
        ]);

        AuditLogger::log($request->user(), 'add_allergy', 'patient', $patient->id, $request->input('substance'));

        return response()->json(new AllergyResource($allergy), 201);
    }

    /**
     * POST /api/patients/merge
     * Roles: records_officer, admin
     * Body: { keep_patient_id, duplicate_patient_id }
     */
    public function merge(Request $request)
    {
        $keepId = $request->input('keep_patient_id');
        $mergeId = $request->input('duplicate_patient_id');

        if (! $keepId || ! $mergeId || $keepId == $mergeId) {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        $duplicate = Patient::findOrFail($mergeId);
        Patient::findOrFail($keepId);

        $duplicate->update(['merged_into_patient_id' => $keepId]);
        Encounter::where('patient_id', $mergeId)->update(['patient_id' => $keepId]);

        AuditLogger::log($request->user(), 'merge_patients', 'patient', $keepId, "merged {$mergeId} into {$keepId}");

        return response()->json(['message' => 'Patients merged']);
    }

    /**
     * GET /api/patients/{patient}/history
     */
    public function history(Patient $patient)
    {
        $encounters = Encounter::where('patient_id', $patient->id)->latest()->get();

        $result = $encounters->map(function (Encounter $e) {
            $d = (new EncounterResource($e))->toArray(request());
            $d['referral'] = in_array($e->stage, Encounter::CLOSED_STAGES, true) ? null : $e->activeReferral()?->toArray();

            return $d;
        });

        return response()->json($result);
    }
    /**
     * GET /api/patients/{patient}/export
     * Full, printable PDF of everything the system holds on this patient.
     * Requires: composer require barryvdh/laravel-dompdf
     */
    public function exportPdf(Request $request, Patient $patient)
    {
        $patient->load('allergies');
        $encounters = Encounter::where('patient_id', $patient->id)
            ->with([
                'vitals', 'clinicalNotes', 'orders',
                'labOrders.result', 'imagingOrders.report',
                'prescriptions.administrations', 'referrals.referredBy', 'invoices',
            ])
            ->latest()
            ->get();

        $age = null;
        if ($patient->date_of_birth) {
            $age = $patient->date_of_birth->age;
        } elseif ($patient->estimated_age !== null) {
            $age = $patient->estimated_age;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.patient-record', [
            'patient' => $patient,
            'allergies' => $patient->allergies,
            'encounters' => $encounters,
            'age' => $age,
            'generatedAt' => now()->format('Y-m-d H:i \U\T\C'),
            'generatedByName' => $request->user()->full_name,
        ]);

        AuditLogger::log($request->user(), 'export_patient_record', 'patient', $patient->id);

        return $pdf->download("nullcare-{$patient->patient_uid}-full-record.pdf");
    }

}
