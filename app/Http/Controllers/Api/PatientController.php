<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PatientController extends Controller
{
    public function __construct(private PatientDuplicateService $duplicates) {}

    /**
     * GET /api/patients?q=&status=not_completed|completed
     */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $status = $request->query('status');

        $patients = Patient::query()
            ->whereNull('is_duplicate_of')
            ->when($status, fn ($query) => $query->where('completion_status', $status))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('patient_number', 'like', "%{$q}%")
                        ->orWhere('national_id', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"]);
                });
            })
            ->orderByDesc('created_at')
            ->get();

        return PatientResource::collection($patients);
    }

    /**
     * POST /api/patients/check-duplicate
     * Body: { given_name, family_name, national_id }
     * Returns a plain array (not paginated) — Reception.jsx renders it directly.
     */
    public function checkDuplicate(Request $request)
    {
        $matches = $this->duplicates->findPossibleMatches([
            'first_name' => $request->input('given_name'),
            'last_name' => $request->input('family_name'),
            'national_id' => $request->input('national_id'),
        ]);

        return PatientResource::collection($matches);
    }

    /**
     * POST /api/patients
     * Registers a new patient. Idempotent on client_uuid: if the same
     * client_uuid is submitted twice (offline retry / sync replay), the
     * existing record is returned rather than creating a duplicate.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'given_name' => ['required', 'string', 'max:100'],
            'family_name' => ['required', 'string', 'max:100'],
            'sex' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'estimated_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:20'],
            'village' => ['nullable', 'string', 'max:100'],
            'traditional_authority' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'patient_category' => ['required', 'in:outpatient,inpatient,student,staff,private,emergency,research,referred'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Idempotency: a retried/replayed offline write with the same
        // client_uuid returns the existing record instead of duplicating.
        if (! empty($data['client_uuid'])) {
            $existing = Patient::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return new PatientResource($existing);
            }
        }

        $patient = DB::transaction(function () use ($data, $request) {
            return Patient::create([
                'client_uuid' => $data['client_uuid'] ?? null,
                'patient_number' => $this->generatePatientNumber(),
                'national_id' => $data['national_id'] ?? null,
                'first_name' => $data['given_name'],
                'last_name' => $data['family_name'],
                'gender' => $data['sex'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'age_estimate' => $data['estimated_age'] ?? null,
                'phone' => $data['phone'] ?? null,
                'village' => $data['village'] ?? null,
                'traditional_authority' => $data['traditional_authority'] ?? null,
                'district' => $data['district'] ?? null,
                'region' => $data['region'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_relationship' => $data['guardian_relationship'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'patient_category' => $data['patient_category'],
                'consent_care' => true,
                'completion_status' => 'not_completed',
                'registered_by' => $request->user()->id,
            ]);
        });

        return response()->json(new PatientResource($patient), 201);
    }

    /**
     * GET /api/patients/{patient}
     */
    public function show(Patient $patient)
    {
        $patient->load('allergies');

        return new PatientResource($patient);
    }

    /**
     * GET /api/patients/{patient}/history
     */
    public function history(Patient $patient)
    {
        $encounters = $patient->encounters()->latest()->get();

        return EncounterResource::collection($encounters);
    }

    /**
     * POST /api/patients/{patient}/allergies
     * Body: { substance, reaction, severity }
     */
    public function storeAllergy(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'substance' => ['required', 'string', 'max:150'],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', 'in:mild,moderate,severe'],
        ]);

        $allergy = $patient->allergies()->create([
            'allergen' => $validated['substance'],
            'reaction' => $validated['reaction'] ?? null,
            'severity' => $validated['severity'],
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json($allergy, 201);
    }

    private function generatePatientNumber(): string
    {
        $year = now()->format('Y');

        do {
            $sequence = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $candidate = "NC-{$year}-{$sequence}";
        } while (Patient::where('patient_number', $candidate)->exists());

        return $candidate;
    }
}
