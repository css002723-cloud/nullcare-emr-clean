<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Services\DiagnosisSuggestionService;

class DiagnosisSuggestionController extends Controller
{
    public function __construct(private DiagnosisSuggestionService $service) {}

    /**
     * GET /api/encounters/{encounter}/diagnosis-suggestions
     * Returns candidate diagnoses for the clinician to review — does NOT
     * write anything. The clinician still confirms via the normal
     * PATCH /encounters/{encounter} to actually set diagnosis/diagnosis_code.
     */
    public function index(Encounter $encounter)
    {
        return response()->json([
            'suggestions' => $this->service->suggestFor($encounter),
            'disclaimer' => 'These are pattern-based suggestions for clinician review only, not a diagnosis. Confirm clinically before recording.',
        ]);
    }
}
