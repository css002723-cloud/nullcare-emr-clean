<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LabController extends Controller
{
    /**
     * GET /api/lab/catalog
     */
    public function catalog()
    {
        return response()->json(LabOrder::CATALOG);
    }

    /**
     * GET /api/lab/orders?status=&encounter_id=&patient_id=
     *
     * Returns enriched laboratory orders including:
     * - Patient name
     * - Patient ID
     * - Age
     * - Sex
     * - Patient category
     * - Visit type
     * - Emergency status
     * - Priority
     * - MRN
     * - Encounter number
     * - Referring doctor
     * - Doctor's clinical notes
     * - Latest laboratory result
     */
    public function index(Request $request)
    {
        $query = LabOrder::query();

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->query('status')
            );
        }

        if ($request->filled('encounter_id')) {
            $query->where(
                'encounter_id',
                $request->query('encounter_id')
            );
        }

        if ($request->filled('patient_id')) {
            $query->where(
                'patient_id',
                $request->query('patient_id')
            );
        }

        /*
         * Load patient and encounter.
         */
        $orders = $query
            ->with([
                'patient',
                'encounter',
            ])
            ->latest()
            ->get();

        $result = $orders->map(function (LabOrder $o) {

            $d = $o->toArray();

            $patient = $o->patient;
            $encounter = $o->encounter;


            /*
             * =====================================================
             * PATIENT NAME
             * =====================================================
             */

            $d['patient_name'] =
                $patient?->full_name;


            /*
             * =====================================================
             * PATIENT ID
             * =====================================================
             */

            $d['patient_id'] =
                $patient?->patient_uid
                ?? $patient?->patient_id
                ?? $patient?->identifier
                ?? $patient?->mrn
                ?? $o->patient_id;

            $d['patient_identifier'] =
                $d['patient_id'];


            /*
             * =====================================================
             * AGE
             * =====================================================
             */

            $d['age'] = null;

            if ($patient?->date_of_birth) {

                try {

                    $d['age'] = Carbon::parse(
                        $patient->date_of_birth
                    )->age;

                } catch (\Throwable $e) {

                    $d['age'] = null;
                }
            }

            /*
             * Fallback for estimated age.
             */
            if (
                $d['age'] === null &&
                $patient?->estimated_age !== null
            ) {
                $d['age'] =
                    $patient->estimated_age;
            }


            /*
             * =====================================================
             * SEX
             * =====================================================
             */

            $d['sex'] =
                $patient?->sex
                ?? $patient?->gender
                ?? null;


            /*
             * =====================================================
             * PATIENT CATEGORY
             * =====================================================
             */

            $d['patient_category'] =
                $encounter?->visit_type
                ?? $patient?->patient_category
                ?? $patient?->category
                ?? 'outpatient';


            /*
             * Visit type.
             */

            $d['visit_type'] =
                $encounter?->visit_type;


            /*
             * =====================================================
             * EMERGENCY STATUS
             * =====================================================
             */

            $d['is_emergency'] =
                (bool) (
                    $encounter?->is_emergency
                    ?? false
                );


            /*
             * =====================================================
             * PRIORITY
             * =====================================================
             */

            $d['priority'] =
                $encounter?->priority
                ?? $o->priority
                ?? 'routine';


            /*
             * =====================================================
             * ENCOUNTER INFORMATION
             * =====================================================
             */

            $d['encounter_id'] =
                $encounter?->id
                ?? $o->encounter_id;

            $d['encounter_number'] =
                $encounter?->encounter_number;

            /*
             * MRN is visit-specific.
             */

            $d['mrn'] =
                $encounter?->mrn;


            /*
             * =====================================================
             * REFERRING / ORDERING DOCTOR
             * =====================================================
             *
             * The doctor who actually ordered the laboratory
             * investigation is stored in LabOrder.ordered_by.
             *
             * Your store() method already saves:
             *
             * 'ordered_by' => $request->user()->id
             *
             */

            $referringDoctor = null;

            if ($o->ordered_by) {

                $referringDoctor =
                    User::find($o->ordered_by);
            }

            $d['referring_doctor_id'] =
                $referringDoctor?->id;

            /*
             * Try the common user name fields.
             */

            $d['referring_doctor'] =
                $referringDoctor?->name
                ?? $referringDoctor?->full_name
                ?? $referringDoctor?->username
                ?? null;


            /*
             * Also return a few aliases so the frontend
             * can easily use whichever naming convention
             * is preferred.
             */

            $d['referring_doctor_name'] =
                $d['referring_doctor'];

            $d['ordered_by'] =
                $o->ordered_by;


            /*
             * =====================================================
             * DOCTOR'S CLINICAL NOTES
             * =====================================================
             *
             * Clinical notes are stored in ClinicalNote.
             *
             * We retrieve the latest note written by a doctor
             * for this particular encounter.
             */

            $doctorNote = null;

            if ($encounter) {

                $doctorNote =
                    ClinicalNote::where(
                        'encounter_id',
                        $encounter->id
                    )
                        ->where(
                            'author_role',
                            'doctor'
                        )
                        ->latest()
                        ->first();
            }


            /*
             * =====================================================
             * BUILD DOCTOR NOTE
             * =====================================================
             */

            $noteParts = [];


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->presenting_complaint
                )
            ) {

                $noteParts[] =
                    "Presenting Complaint: " .
                    $doctorNote->presenting_complaint;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->history_of_presenting_illness
                )
            ) {

                $noteParts[] =
                    "History of Presenting Illness: " .
                    $doctorNote->history_of_presenting_illness;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->past_medical_history
                )
            ) {

                $noteParts[] =
                    "Past Medical History: " .
                    $doctorNote->past_medical_history;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->medication_history
                )
            ) {

                $noteParts[] =
                    "Medication History: " .
                    $doctorNote->medication_history;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->allergy_history
                )
            ) {

                $noteParts[] =
                    "Allergy History: " .
                    $doctorNote->allergy_history;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->review_of_systems
                )
            ) {

                $noteParts[] =
                    "Review of Systems: " .
                    $doctorNote->review_of_systems;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->examination_findings
                )
            ) {

                $noteParts[] =
                    "Examination Findings: " .
                    $doctorNote->examination_findings;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->diagnosis
                )
            ) {

                $noteParts[] =
                    "Diagnosis: " .
                    $doctorNote->diagnosis;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->differential_diagnosis
                )
            ) {

                $noteParts[] =
                    "Differential Diagnosis: " .
                    $doctorNote->differential_diagnosis;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->plan
                )
            ) {

                $noteParts[] =
                    "Plan: " .
                    $doctorNote->plan;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->follow_up_plan
                )
            ) {

                $noteParts[] =
                    "Follow-up Plan: " .
                    $doctorNote->follow_up_plan;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->history
                )
            ) {

                $noteParts[] =
                    "Clinical History: " .
                    $doctorNote->history;
            }


            if (
                $doctorNote &&
                !empty(
                    $doctorNote->body
                )
            ) {

                $noteParts[] =
                    "Doctor's Note: " .
                    $doctorNote->body;
            }


            /*
             * Combine all available clinical information.
             */

            $doctorNoteText =
                !empty($noteParts)
                    ? implode(
                        "\n\n",
                        $noteParts
                    )
                    : null;


            /*
             * =====================================================
             * SEND DOCTOR NOTES TO FRONTEND
             * =====================================================
             */

            $d['doctor_note'] =
                $doctorNoteText;

            $d['doctor_notes'] =
                $doctorNoteText;

            $d['doctor_note_type'] =
                $doctorNote?->note_type;

            $d['doctor_id'] =
                $doctorNote?->author_id;

            $d['doctor_note_date'] =
                $doctorNote?->created_at;


            /*
             * =====================================================
             * LATEST LAB RESULT
             * =====================================================
             */

            $d['result'] =
                LabResult::where(
                    'lab_order_id',
                    $o->id
                )
                    ->latest()
                    ->first();


            return $d;
        });

        return response()->json($result);
    }


    /**
     * POST /api/lab/orders
     *
     * Roles:
     * doctor, nurse, admin
     */
    public function store(Request $request)
    {
        if (
            ! $request->filled('encounter_id') ||
            ! $request->filled('test_code')
        ) {

            return response()->json([
                'error' =>
                    'missing_fields',

                'message' =>
                    'encounter_id and test_code are required'

            ], 400);
        }


        $encounter =
            Encounter::findOrFail(
                $request->input(
                    'encounter_id'
                )
            );


        $loinc =
            LabOrder::CATALOG[
                $request->input(
                    'test_code'
                )
            ] ?? [];


        $labOrder =
            DB::transaction(
                function () use (
                    $request,
                    $encounter,
                    $loinc
                ) {

                    /*
                     * Generic order.
                     */

                    $genericOrder =
                        Order::create([

                            'encounter_id' =>
                                $encounter->id,

                            'patient_id' =>
                                $encounter->patient_id,

                            'order_type' =>
                                'lab',

                            'details' =>
                                $request->input(
                                    'test_code'
                                ),

                            'priority' =>
                                $request->input(
                                    'priority',
                                    'routine'
                                ),

                            'target_department' =>
                                'laboratory',

                            /*
                             * This identifies the doctor/user
                             * who ordered the test.
                             */
                            'ordered_by' =>
                                $request->user()->id,
                        ]);


                    /*
                     * Laboratory order.
                     */

                    return LabOrder::create([

                        'order_id' =>
                            $genericOrder->id,

                        'encounter_id' =>
                            $encounter->id,

                        'patient_id' =>
                            $encounter->patient_id,

                        'test_code' =>
                            $request->input(
                                'test_code'
                            ),

                        'loinc_code' =>
                            $loinc[
                                'loinc_code'
                            ] ?? null,

                        'loinc_display' =>
                            $loinc[
                                'loinc_display'
                            ] ?? null,

                        'specimen_type' =>
                            $request->input(
                                'specimen_type'
                            ),

                        'barcode' =>
                            IdGenerator::barcode(),

                        'priority' =>
                            $request->input(
                                'priority',
                                'routine'
                            ),

                        /*
                         * Referring / ordering doctor.
                         */
                        'ordered_by' =>
                            $request->user()->id,

                        'client_uuid' =>
                            $request->input(
                                'client_uuid'
                            ),
                    ]);
                }
            );


        AuditLogger::log(
            $request->user(),
            'order_lab_test',
            'encounter',
            $encounter->id,
            $request->input(
                'test_code'
            )
        );


        return response()->json(
            $labOrder,
            201
        );
    }


    /**
     * POST /api/lab/orders/{labOrder}/collect
     *
     * Roles:
     * lab_tech, nurse, admin
     */
    public function collect(
        Request $request,
        LabOrder $labOrder
    ) {

        $labOrder->update([

            'status' =>
                'collected',

            'collected_by' =>
                $request->user()->id,

            'collected_at' =>
                now(),
        ]);


        AuditLogger::log(
            $request->user(),
            'collect_specimen',
            'lab_order',
            $labOrder->id
        );


        return response()->json(
            $labOrder
        );
    }


    /**
     * POST /api/lab/orders/{labOrder}/receive
     *
     * Roles:
     * lab_tech, admin
     */
    public function receive(
        Request $request,
        LabOrder $labOrder
    ) {

        $labOrder->update([

            'status' =>
                'received',

            'received_at' =>
                now(),
        ]);


        AuditLogger::log(
            $request->user(),
            'receive_specimen',
            'lab_order',
            $labOrder->id
        );


        return response()->json(
            $labOrder
        );
    }


    /**
     * POST /api/lab/orders/{labOrder}/result
     *
     * Roles:
     * lab_tech, admin
     */
    public function storeResult(
        Request $request,
        LabOrder $labOrder
    ) {

        $result =
            LabResult::create([

                'lab_order_id' =>
                    $labOrder->id,

                'result_value' =>
                    $request->input(
                        'result_value'
                    ),

                'unit' =>
                    $request->input(
                        'unit'
                    ),

                'reference_range' =>
                    $request->input(
                        'reference_range'
                    ),

                'is_critical' =>
                    $request->input(
                        'is_critical',
                        false
                    ),

                'is_abnormal' =>
                    $request->input(
                        'is_abnormal',
                        false
                    ),

                'interpretation' =>
                    $request->input(
                        'interpretation'
                    ),

                'entered_by' =>
                    $request->user()->id,

                'client_uuid' =>
                    $request->input(
                        'client_uuid'
                    ),
            ]);


        $labOrder->update([
            'status' =>
                'resulted'
        ]);


        AuditLogger::log(
            $request->user(),
            'enter_lab_result',
            'lab_order',
            $labOrder->id,
            'critical=' .
                (
                    $result->is_critical
                        ? 'true'
                        : 'false'
                )
        );


        return response()->json(
            $result,
            201
        );
    }


    /**
     * POST /api/lab/results/{labResult}/verify
     *
     * Roles:
     * lab_tech, admin
     */
    public function verify(
        Request $request,
        LabResult $labResult
    ) {

        $labResult->update([

            'verified_by' =>
                $request->user()->id,

            'verified_at' =>
                now(),
        ]);


        LabOrder::where(
            'id',
            $labResult->lab_order_id
        )->update([

            'status' =>
                'verified'
        ]);


        AuditLogger::log(
            $request->user(),
            'verify_lab_result',
            'lab_result',
            $labResult->id
        );


        return response()->json(
            $labResult
        );
    }


    /**
     * POST /api/lab/results/{labResult}/acknowledge-critical
     *
     * Roles:
     * doctor, nurse, admin
     */
    public function acknowledgeCritical(
        Request $request,
        LabResult $labResult
    ) {

        $labResult->update([

            'critical_alert_acknowledged' =>
                true
        ]);


        AuditLogger::log(
            $request->user(),
            'acknowledge_critical_result',
            'lab_result',
            $labResult->id
        );


        return response()->json(
            $labResult
        );
    }


    /**
     * GET /api/lab/critical-unacknowledged
     *
     * Returns enriched critical results.
     */
    public function criticalUnacknowledged()
    {
        $results =
            LabResult::where(
                'is_critical',
                true
            )
                ->where(
                    'critical_alert_acknowledged',
                    false
                )
                ->with([
                    'labOrder.patient',
                    'labOrder.encounter',
                ])
                ->get()
                ->map(function (
                    LabResult $r
                ) {

                    $lo =
                        $r->labOrder;

                    $patient =
                        $lo?->patient;

                    $encounter =
                        $lo?->encounter;


                    /*
                     * =================================================
                     * AGE
                     * =================================================
                     */

                    $age = null;

                    if (
                        $patient?->date_of_birth
                    ) {

                        try {

                            $age =
                                Carbon::parse(
                                    $patient->date_of_birth
                                )->age;

                        } catch (
                            \Throwable $e
                        ) {

                            $age = null;
                        }
                    }


                    if (
                        $age === null &&
                        $patient?->estimated_age !== null
                    ) {

                        $age =
                            $patient->estimated_age;
                    }


                    /*
                     * =================================================
                     * REFERRING DOCTOR
                     * =================================================
                     */

                    $referringDoctor = null;

                    if ($lo?->ordered_by) {

                        $referringDoctor =
                            User::find(
                                $lo->ordered_by
                            );
                    }


                    /*
                     * =================================================
                     * DOCTOR'S NOTE
                     * =================================================
                     */

                    $doctorNote = null;

                    if ($encounter) {

                        $doctorNote =
                            ClinicalNote::where(
                                'encounter_id',
                                $encounter->id
                            )
                                ->where(
                                    'author_role',
                                    'doctor'
                                )
                                ->latest()
                                ->first();
                    }


                    $noteParts = [];


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->presenting_complaint
                        )
                    ) {

                        $noteParts[] =
                            "Presenting Complaint: " .
                            $doctorNote->presenting_complaint;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->history_of_presenting_illness
                        )
                    ) {

                        $noteParts[] =
                            "History of Presenting Illness: " .
                            $doctorNote->history_of_presenting_illness;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->examination_findings
                        )
                    ) {

                        $noteParts[] =
                            "Examination Findings: " .
                            $doctorNote->examination_findings;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->diagnosis
                        )
                    ) {

                        $noteParts[] =
                            "Diagnosis: " .
                            $doctorNote->diagnosis;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->differential_diagnosis
                        )
                    ) {

                        $noteParts[] =
                            "Differential Diagnosis: " .
                            $doctorNote->differential_diagnosis;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->plan
                        )
                    ) {

                        $noteParts[] =
                            "Plan: " .
                            $doctorNote->plan;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->follow_up_plan
                        )
                    ) {

                        $noteParts[] =
                            "Follow-up Plan: " .
                            $doctorNote->follow_up_plan;
                    }


                    if (
                        $doctorNote &&
                        !empty(
                            $doctorNote->body
                        )
                    ) {

                        $noteParts[] =
                            "Doctor's Note: " .
                            $doctorNote->body;
                    }


                    $doctorNoteText =
                        !empty($noteParts)
                            ? implode(
                                "\n\n",
                                $noteParts
                            )
                            : null;


                    return [

                        'id' =>
                            $r->id,

                        'lab_order_id' =>
                            $r->lab_order_id,

                        'result_value' =>
                            $r->result_value,

                        'unit' =>
                            $r->unit,

                        'reference_range' =>
                            $r->reference_range,

                        'interpretation' =>
                            $r->interpretation,

                        'is_critical' =>
                            (bool)
                            $r->is_critical,

                        'critical_alert_acknowledged' =>
                            (bool)
                            $r->critical_alert_acknowledged,

                        'entered_by' =>
                            $r->entered_by,


                        /*
                         * Lab order.
                         */

                        'lab_order' =>
                            $lo
                                ? [

                                    'id' =>
                                        $lo->id,

                                    'encounter_id' =>
                                        $lo->encounter_id,

                                    'patient_id' =>
                                        $lo->patient_id,

                                    'loinc_display' =>
                                        $lo->loinc_display,

                                    'test_code' =>
                                        $lo->test_code,

                                    'barcode' =>
                                        $lo->barcode,

                                    'priority' =>
                                        $lo->priority,

                                ]
                                : null,


                        /*
                         * Patient.
                         */

                        'patient_name' =>
                            $patient?->full_name,

                        'patient_id' =>
                            $patient?->patient_uid
                            ?? $patient?->patient_id
                            ?? $patient?->identifier
                            ?? $patient?->mrn
                            ?? $lo?->patient_id,

                        'age' =>
                            $age,

                        'sex' =>
                            $patient?->sex
                            ?? $patient?->gender,

                        'patient_category' =>
                            $encounter?->visit_type
                            ?? $patient?->patient_category
                            ?? $patient?->category
                            ?? 'outpatient',


                        /*
                         * Encounter.
                         */

                        'encounter_number' =>
                            $encounter?->encounter_number,

                        'mrn' =>
                            $encounter?->mrn,

                        'visit_type' =>
                            $encounter?->visit_type,

                        'is_emergency' =>
                            (bool) (
                                $encounter?->is_emergency
                                ?? false
                            ),


                        /*
                         * Referring doctor.
                         */

                        'referring_doctor_id' =>
                            $referringDoctor?->id,

                        'referring_doctor' =>
                            $referringDoctor?->name
                            ?? $referringDoctor?->full_name
                            ?? $referringDoctor?->username
                            ?? null,

                        'referring_doctor_name' =>
                            $referringDoctor?->name
                            ?? $referringDoctor?->full_name
                            ?? $referringDoctor?->username
                            ?? null,

                        'ordered_by' =>
                            $lo?->ordered_by,


                        /*
                         * Doctor's notes.
                         */

                        'doctor_note' =>
                            $doctorNoteText,

                        'doctor_notes' =>
                            $doctorNoteText,

                        'doctor_note_type' =>
                            $doctorNote?->note_type,

                        'doctor_id' =>
                            $doctorNote?->author_id,

                        'doctor_note_date' =>
                            $doctorNote?->created_at,
                    ];
                });


        return response()->json(
            $results
        );
    }
}