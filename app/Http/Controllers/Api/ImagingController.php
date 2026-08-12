<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\ImagingOrder;
use App\Models\ImagingReport;
use App\Models\Order;
use App\Models\Patient;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImagingController extends Controller
{
    /** DICOM modality codes: CR=X-ray, CT=CT scan, US=Ultrasound, MR=MRI, DX=Digital X-ray. */
    private const MODALITIES = ['CR', 'CT', 'US', 'MR', 'DX'];

    public function modalities()
    {
        return response()->json(self::MODALITIES);
    }

    /**
     * GET /api/imaging/orders?status=&encounter_id=
     */
    public function index(Request $request)
    {
        $query = ImagingOrder::query()->with(['patient', 'encounter']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->query('encounter_id'));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->query('patient_id'));
        }

        $orders = $query->latest()->get();

        $result = $orders->map(function (ImagingOrder $o) {
            $d = $o->toArray();
            $d['report'] = ImagingReport::where('imaging_order_id', $o->id)->first();

            $patient = $o->patient;
            $d['patient_name'] = $patient ? "{$patient->given_name} {$patient->family_name}" : null;
            $d['patient_uid'] = $patient?->patient_uid;
            $d['mrn'] = $o->encounter?->mrn;

            $consultNote = ClinicalNote::where('encounter_id', $o->encounter_id)
                ->where('note_type', 'consult')
                ->latest()
                ->first();

            if (! $consultNote) {
                $consultNote = ClinicalNote::where('encounter_id', $o->encounter_id)
                    ->where('note_type', 'history_physical')
                    ->latest()
                    ->first();
            }

            $d['consultation_note'] = $consultNote?->body
                ?: $consultNote?->examination_findings
                ?: $consultNote?->presenting_complaint;

            return $d;
        });

        return response()->json($result);
    }

    /**
     * POST /api/imaging/orders
     * Roles: doctor, admin
     * Safety check: ionizing-radiation modalities (CT/CR/DX) on a female
     * patient without confirmed pregnancy status get a system flag
     * appended to the safety checklist notes — not blocked, flagged.
     */
    public function store(Request $request)
    {
        if (! $request->filled('encounter_id') || ! $request->filled('modality')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id and modality are required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));
        $patient = Patient::find($encounter->patient_id);

        $safetyNotes = (string) $request->input('safety_checklist_notes', '');
        $pregnancyChecked = (bool) $request->input('is_pregnancy_checked', false);
        $modality = $request->input('modality');

        if (in_array($modality, ['CT', 'CR', 'DX'], true) && $patient && $patient->sex === 'female' && ! $pregnancyChecked) {
            $safetyNotes .= ' [SYSTEM FLAG: pregnancy status not confirmed prior to ionizing radiation study]';
        }

        $imagingOrder = DB::transaction(function () use ($request, $encounter, $modality, $pregnancyChecked, $safetyNotes) {
            $genericOrder = Order::create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'order_type' => 'imaging',
                'details' => $request->input('study_description'),
                'priority' => $request->input('priority', 'routine'),
                'target_department' => 'imaging',
                'ordered_by' => $request->user()->id,
            ]);

            return ImagingOrder::create([
                'order_id' => $genericOrder->id,
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'modality' => $modality,
                'study_description' => $request->input('study_description'),
                'body_site' => $request->input('body_site'),
                'clinical_indication' => $request->input('clinical_indication'),
                'accession_number' => 'ACC'.random_int(10000000, 99999999),
                'is_pregnancy_checked' => $pregnancyChecked,
                'safety_checklist_notes' => $safetyNotes,
                'priority' => $request->input('priority', 'routine'),
                'ordered_by' => $request->user()->id,
                'client_uuid' => $request->input('client_uuid'),
            ]);
        });

        AuditLogger::log(
            $request->user(), 'order_imaging', 'encounter', $encounter->id,
            "{$modality} {$request->input('study_description', '')}"
        );

        return response()->json($imagingOrder, 201);
    }

    /**
     * PUT /api/imaging/orders/{imagingOrder}/status
     * Roles: radiologist, admin
     */
    public function updateStatus(Request $request, ImagingOrder $imagingOrder)
    {
        $imagingOrder->status = $request->input('status', $imagingOrder->status);
        if ($request->filled('study_instance_uid')) {
            $imagingOrder->study_instance_uid = $request->input('study_instance_uid');
        }
        $imagingOrder->save();

        AuditLogger::log($request->user(), 'update_imaging_status', 'imaging_order', $imagingOrder->id, $imagingOrder->status);

        return response()->json($imagingOrder);
    }

    /**
     * POST /api/imaging/orders/{imagingOrder}/report
     * Roles: radiologist, admin
     */
    public function storeReport(Request $request, ImagingOrder $imagingOrder)
    {
        $report = ImagingReport::create([
            'imaging_order_id' => $imagingOrder->id,
            'findings' => $request->input('findings'),
            'impression' => $request->input('impression'),
            'is_critical_finding' => $request->input('is_critical_finding', false),
            'reported_by' => $request->user()->id,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        $imagingOrder->update(['status' => 'reported']);

        AuditLogger::log(
            $request->user(), 'create_imaging_report', 'imaging_order', $imagingOrder->id,
            'critical='.($report->is_critical_finding ? 'true' : 'false')
        );

        return response()->json($report, 201);
    }

    /**
     * POST /api/imaging/reports/{imagingReport}/review
     * Roles: doctor, admin
     */
    public function reviewReport(Request $request, ImagingReport $imagingReport)
    {
        $imagingReport->update(['reviewed_by_clinician' => $request->user()->id, 'reviewed_at' => now()]);

        AuditLogger::log($request->user(), 'review_imaging_report', 'imaging_report', $imagingReport->id);

        return response()->json($imagingReport);
    }
}
