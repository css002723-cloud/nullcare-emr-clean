<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalNote;
use App\Models\Encounter;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ClinicalNoteController extends Controller
{
    /**
     * GET /api/encounters/{encounter}/notes
     */
    public function index(Encounter $encounter)
    {
        $notes = ClinicalNote::where('encounter_id', $encounter->id)->latest()->get();

        return response()->json($notes);
    }

    /**
     * POST /api/encounters/{encounter}/notes
     * Roles: doctor, nurse, admin
     * Creating a note IS signing it — there's no separate draft/unsigned
     * state, so the signature is stamped at creation time.
     */
    public function store(Request $request, Encounter $encounter)
    {
        $note = ClinicalNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'note_type' => $request->input('note_type', 'progress'),
            'clinic_template' => $request->input('clinic_template'),
            'presenting_complaint' => $request->input('presenting_complaint'),
            'history_of_presenting_illness' => $request->input('history_of_presenting_illness'),
            'past_medical_history' => $request->input('past_medical_history'),
            'past_surgical_history' => $request->input('past_surgical_history'),
            'medication_history' => $request->input('medication_history'),
            'allergy_history' => $request->input('allergy_history'),
            'social_history' => $request->input('social_history'),
            'family_history' => $request->input('family_history'),
            'review_of_systems' => $request->input('review_of_systems'),
            'examination_findings' => $request->input('examination_findings'),
            'diagnosis' => $request->input('diagnosis'),
            'icd_code' => $request->input('icd_code'),
            'differential_diagnosis' => $request->input('differential_diagnosis'),
            'plan' => $request->input('plan'),
            'follow_up_plan' => $request->input('follow_up_plan'),
            'body' => $request->input('body'),
            'history' => $request->input('history'),
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->role,
            'signed' => true,
            'signed_by_name' => $request->user()->full_name,
            'signed_at' => now(),
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log(
            $request->user(), 'create_note', 'encounter', $encounter->id,
            "type={$request->input('note_type')} template={$request->input('clinic_template')}"
        );

        return response()->json($note, 201);
    }
}
