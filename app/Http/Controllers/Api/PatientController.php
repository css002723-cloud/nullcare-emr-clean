<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Services\PatientDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PatientController extends Controller
{
    public function __construct(private PatientDuplicateService $duplicates) {}

    /**
     * GET /api/patients?search=...
     * Search by name, patient_number, national_id, or phone.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $patients = Patient::query()
            ->whereNull('is_duplicate_of')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('patient_number', 'like', "%{$search}%")
                        ->orWhere('national_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($patients);
    }

    /**
     * GET /api/patients/check-duplicates
     * Called by the frontend BEFORE final submit, so reception can see
     * "this might already be a patient" and choose to link instead of
     * creating a new record. Accepts the same fields as StorePatientRequest.
     */
    public function checkDuplicates(Request $request)
    {
        $matches = $this->duplicates->findPossibleMatches($request->only([
            'national_id', 'phone', 'first_name', 'last_name', 'date_of_birth',
        ]));

        return response()->json(['possible_matches' => $matches]);
    }

    /**
     * POST /api/patients
     * Registers a new patient. Generates the hospital number server-side
     * so it's guaranteed unique and consistently formatted.
     */
    public function store(StorePatientRequest $request)
    {
        $data = $request->validated();

        $patient = DB::transaction(function () use ($data, $request) {
            $data['patient_number'] = $this->generatePatientNumber();
            $data['registered_by'] = $request->user()->id;
            $data['consent_care'] = $data['consent_care'] ?? true;
            $data['consent_teaching'] = $data['consent_teaching'] ?? false;
            $data['consent_research'] = $data['consent_research'] ?? false;

            unset($data['confirm_new_patient']);

            return Patient::create($data);
        });

        return response()->json($patient, 201);
    }

    /**
     * GET /api/patients/{patient}
     */
    public function show(Patient $patient)
    {
        $patient->load(['allergies', 'encounters' => function ($q) {
            $q->latest()->limit(10);
        }]);

        return response()->json($patient);
    }

    /**
     * Format: NC-YYYY-XXXXXX (NC = NullCare), e.g. NC-2026-000482.
     * Re-checks uniqueness in a loop to guard against a rare race on
     * the count-based suffix under concurrent registrations.
     */
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
