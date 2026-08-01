<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientExportController extends Controller
{
    /**
     * GET /api/patients/export?mode=internal|research
     *
     * "internal" — full operational record (records officer use, e.g.
     * continuity of care, hospital administration). Includes MRN and name.
     *
     * "research" — de-identified: uses ONLY research_id, strips name,
     * national_id, phone, guardian info, and exact village — keeps
     * clinically/demographically useful fields (age, sex, district,
     * region, diagnosis codes) without anything that identifies the
     * person. Also filters to only patients who gave consent_research.
     * This is the mode that satisfies "use the generated unique ID, not
     * anything traceable back to the patient" for research/report use.
     */
    public function export(Request $request)
    {
        $mode = $request->query('mode', 'internal');

        if (! in_array($mode, ['internal', 'research'], true)) {
            return response()->json(['message' => 'mode must be internal or research.'], 422);
        }

        $filename = $mode === 'research'
            ? 'nullcare-research-export-'.now()->format('Ymd-His').'.csv'
            : 'nullcare-patient-export-'.now()->format('Ymd-His').'.csv';

        $callback = function () use ($mode) {
            $handle = fopen('php://output', 'w');

            if ($mode === 'research') {
                fputcsv($handle, ['research_id', 'sex', 'age_estimate', 'date_of_birth_year', 'district', 'region', 'patient_category', 'registered_month']);

                Patient::where('consent_research', true)
                    ->whereNull('is_duplicate_of')
                    ->chunk(200, function ($patients) use ($handle) {
                        foreach ($patients as $p) {
                            fputcsv($handle, [
                                $p->research_id,
                                $p->gender,
                                $p->age_estimate,
                                $p->date_of_birth?->format('Y'), // year only, not full DOB — reduces re-identification risk
                                $p->district,
                                $p->region,
                                $p->patient_category,
                                $p->created_at->format('Y-m'),
                            ]);
                        }
                    });
            } else {
                fputcsv($handle, ['mrn', 'given_name', 'family_name', 'sex', 'date_of_birth', 'phone', 'district', 'village', 'patient_category', 'registered_at']);

                Patient::whereNull('is_duplicate_of')
                    ->chunk(200, function ($patients) use ($handle) {
                        foreach ($patients as $p) {
                            fputcsv($handle, [
                                $p->patient_number,
                                $p->first_name,
                                $p->last_name,
                                $p->gender,
                                $p->date_of_birth?->toDateString(),
                                $p->phone,
                                $p->district,
                                $p->village,
                                $p->patient_category,
                                $p->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
