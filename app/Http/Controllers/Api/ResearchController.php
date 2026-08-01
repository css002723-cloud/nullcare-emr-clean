<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\Patient;
use App\Services\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchController extends Controller
{
    private const CSV_COLUMNS = [
        'pseudo_id', 'age_bucket', 'sex', 'district', 'region', 'patient_category',
        'visit_type', 'priority', 'outcome', 'diagnoses', 'icd_codes',
    ];

    /**
     * GET /api/research/export (records_officer, admin)
     * De-identified CSV, one row per patient-encounter. Only
     * consent_research=true patients included; direct identifiers
     * stripped, age bucketed, location generalized to district/region —
     * matches research.py's de-identification approach exactly.
     */
    public function export(\Illuminate\Http\Request $request)
    {
        $patients = Patient::where('consent_research', true)->whereNull('merged_into_patient_id')->get();
        $rowCount = 0;

        $callback = function () use ($patients, &$rowCount) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::CSV_COLUMNS);

            foreach ($patients as $patient) {
                $baseRow = [
                    'pseudo_id' => 'RES-'.str_pad($patient->id, 6, '0', STR_PAD_LEFT),
                    'age_bucket' => $this->ageBucket($patient),
                    'sex' => $patient->sex,
                    'district' => $patient->district,
                    'region' => $patient->region,
                    'patient_category' => $patient->patient_category,
                ];

                $encounters = Encounter::where('patient_id', $patient->id)->get();

                if ($encounters->isEmpty()) {
                    fputcsv($handle, [...array_values($baseRow), '', '', '', '', '']);
                    $rowCount++;

                    continue;
                }

                foreach ($encounters as $encounter) {
                    $notes = ClinicalNote::where('encounter_id', $encounter->id)->get();
                    $diagnoses = $notes->pluck('diagnosis')->filter()->implode('; ');
                    $icdCodes = $notes->pluck('icd_code')->filter()->implode('; ');

                    fputcsv($handle, [
                        ...array_values($baseRow),
                        $encounter->visit_type, $encounter->priority, $encounter->outcome ?? '',
                        $diagnoses, $icdCodes,
                    ]);
                    $rowCount++;
                }
            }

            fclose($handle);
        };

        AuditLogger::log($request->user(), 'export_research_data', 'research_export', null, "rows={$rowCount}");

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="nullcare-research-export-'.now()->format('Ymd').'.csv"',
        ]);
    }

    /**
     * GET /api/research/consent-summary (records_officer, admin)
     */
    public function consentSummary()
    {
        $total = Patient::whereNull('merged_into_patient_id')->count();
        $consented = Patient::where('consent_research', true)->whereNull('merged_into_patient_id')->count();

        return response()->json([
            'total_patients' => $total,
            'consented_for_research' => $consented,
            'consent_rate_pct' => $total ? round($consented / $total * 100, 1) : 0,
        ]);
    }

    private function ageBucket(Patient $patient): string
    {
        if ($patient->date_of_birth) {
            $age = $patient->date_of_birth->age;
        } elseif ($patient->estimated_age !== null) {
            $age = $patient->estimated_age;
        } else {
            return 'unknown';
        }

        return match (true) {
            $age < 1 => 'under_1',
            $age < 5 => '1_4',
            $age < 18 => '5_17',
            $age < 40 => '18_39',
            $age < 65 => '40_64',
            default => '65_plus',
        };
    }
}
