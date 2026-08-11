<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\ImagingOrder;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function export(Request $request)
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
     * GET /api/research/report-summary
     */
    public function reportSummary(Request $request)
    {
        return response()->json($this->buildReport($request));
    }

    /**
     * GET /api/research/report-export
     */
    public function reportExport(Request $request)
    {
        $report = $this->buildReport($request);
        $start = $request->input('start_date', 'start');
        $end = $request->input('end_date', 'end');
        $filename = "nullcare-report-export-{$start}-{$end}.csv";

        $callback = function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['metric', 'value']);

            foreach (['total_patients', 'new_patients', 'admissions', 'discharges', 'deidentify'] as $field) {
                if (array_key_exists($field, $report)) {
                    $value = $report[$field];
                    if (is_bool($value)) {
                        $value = $value ? 'yes' : 'no';
                    }

                    fputcsv($handle, [$field, $value]);
                }
            }

            if (isset($report['age_buckets'])) {
                fputcsv($handle, []);
                fputcsv($handle, ['age_bucket', 'count']);
                foreach ($report['age_buckets'] as $bucket => $count) {
                    fputcsv($handle, [$bucket, $count]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['department', 'count']);
            foreach ($report['patients_by_department'] as $row) {
                fputcsv($handle, [$row['department'], $row['count']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['diagnosis', 'count']);
            foreach ($report['top_diagnoses'] as $row) {
                fputcsv($handle, [$row['diagnosis'], $row['count']]);
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildReport(Request $request): array
    {
        [$start, $end] = $this->parseDateRange($request);
        $query = $this->buildEncounterQuery($request, $start, $end);

        $patientsByDepartment = (clone $query)
            ->select('current_department', DB::raw('COUNT(*) as count'))
            ->groupBy('current_department')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->current_department ?: '(unknown)',
                'count' => $row->count,
            ])
            ->values();

        $topDiagnoses = ClinicalNote::whereHas('encounter', fn ($query) => $this->applyEncounterFilters($query, $request, $start, $end))
            ->where(function ($query) {
                $query->whereNotNull('diagnosis')->where('diagnosis', '!=', '')
                    ->orWhereNotNull('icd_code')->where('icd_code', '!=', '');
            })
            ->selectRaw("COALESCE(NULLIF(diagnosis, ''), NULLIF(icd_code, '')) as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['diagnosis' => $row->label, 'count' => $row->count])
            ->values();

        $report = [
            'total_patients' => (clone $query)->distinct('patient_id')->count('patient_id'),
            'new_patients' => $this->countNewPatients($request, $start, $end),
            'admissions' => (clone $query)->where('stage', 'admitted')->count(),
            'discharges' => (clone $query)->where('stage', 'discharged')->count(),
            'patients_by_department' => $patientsByDepartment,
            'top_diagnoses' => $topDiagnoses,
            'deidentify' => $request->boolean('deidentify', true),
        ];

        if ($request->input('age') === 'buckets') {
            $report['age_buckets'] = $this->ageBucketDistribution($request, $start, $end);
        }

        return $report;
    }

    private function buildEncounterQuery(Request $request, Carbon $start, Carbon $end)
    {
        return $this->applyEncounterFilters(Encounter::query(), $request, $start, $end);
    }

    private function countNewPatients(Request $request, Carbon $start, Carbon $end): int
    {
        return Patient::whereNull('merged_into_patient_id')
            ->whereHas('encounters', fn ($query) => $this->applyEncounterFilters($query, $request, $start, $end))
            ->whereDoesntHave('encounters', fn ($query) => $query->where('created_at', '<', $start))
            ->count();
    }

    private function ageBucketDistribution(Request $request, Carbon $start, Carbon $end): array
    {
        $buckets = [
            'under_1' => 0,
            '1_4' => 0,
            '5_17' => 0,
            '18_39' => 0,
            '40_64' => 0,
            '65_plus' => 0,
            'unknown' => 0,
        ];

        Patient::whereHas('encounters', fn ($query) => $this->applyEncounterFilters($query, $request, $start, $end))
            ->get()
            ->each(function (Patient $patient) use (&$buckets) {
                $buckets[$this->ageBucket($patient)]++;
            });

        return $buckets;
    }

    private function parseDateRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::createFromTimestamp(0);

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$start, $end];
    }

    private function applyEncounterFilters($query, Request $request, Carbon $start, Carbon $end)
    {
        $query->whereBetween('created_at', [$start, $end]);

        if ($request->filled('sex')) {
            $query->whereHas('patient', fn ($query) => $query->where('sex', $request->input('sex')));
        }

        if ($request->filled('diagnosis')) {
            $term = '%'.trim($request->input('diagnosis')).'%';
            $query->whereHas('clinicalNotes', fn ($query) => $query->where('diagnosis', 'like', $term)->orWhere('icd_code', 'like', $term));
        }

        if ($request->filled('icd')) {
            $term = '%'.trim($request->input('icd')).'%';
            $query->whereHas('clinicalNotes', fn ($query) => $query->where('icd_code', 'like', $term)->orWhere('diagnosis', 'like', $term));
        }

        if ($request->filled('laboratory')) {
            $term = '%'.trim($request->input('laboratory')).'%';
            $query->whereHas('labOrders', fn ($query) => $query->where('test_code', 'like', $term)->orWhere('loinc_display', 'like', $term));
        }

        if ($request->filled('imaging')) {
            $term = '%'.trim($request->input('imaging')).'%';
            $query->whereHas('imagingOrders', fn ($query) => $query->where('modality', 'like', $term)->orWhere('study_description', 'like', $term)->orWhere('clinical_indication', 'like', $term));
        }

        if ($request->filled('treatment')) {
            $term = '%'.trim($request->input('treatment')).'%';
            $query->whereHas('prescriptions', fn ($query) => $query->where('drug_name', 'like', $term));
        }

        if ($request->filled('admission')) {
            $admission = $request->input('admission');
            if ($admission === 'admitted') {
                $query->where('stage', 'admitted');
            } elseif ($admission === 'discharged') {
                $query->where('stage', 'discharged');
            }
        }

        return $query;
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
