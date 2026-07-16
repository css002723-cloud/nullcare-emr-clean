<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicalNoteResource;
use App\Models\Encounter;
use Illuminate\Http\Request;

class ClinicalNoteController extends Controller
{
    /**
     * GET /api/encounters/{encounter}/notes
     */
    public function index(Encounter $encounter)
    {
        return ClinicalNoteResource::collection($encounter->clinicalNotes()->latest()->get());
    }

    /**
     * POST /api/encounters/{encounter}/notes
     * Body: { note_type, diagnosis, plan, body, client_uuid }
     */
    public function store(Request $request, Encounter $encounter)
    {
        $validated = $request->validate([
            'note_type' => ['required', 'in:history_physical,progress,nursing,consult,discharge_summary'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        if (! empty($validated['client_uuid'])) {
            $existing = $encounter->clinicalNotes()->where('client_uuid', $validated['client_uuid'])->first();
            if ($existing) {
                return new ClinicalNoteResource($existing);
            }
        }

        $note = $encounter->clinicalNotes()->create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json(new ClinicalNoteResource($note), 201);
    }
}
