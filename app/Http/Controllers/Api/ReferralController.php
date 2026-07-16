<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReferralResource;
use App\Models\Encounter;
use App\Models\Referral;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * POST /api/referrals
     * Body: { encounter_id, to_department, reason, client_uuid }
     * Also moves the encounter's current_department, so the "Currently in"
     * badge on the referral panel reflects the move immediately.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'encounter_id' => ['required', 'integer', 'exists:encounters,id'],
            'to_department' => ['required', 'in:laboratory,imaging,pharmacy,dialysis,ward,billing,consultation'],
            'reason' => ['nullable', 'string'],
            'client_uuid' => ['nullable', 'string', 'max:36'],
        ]);

        if (! empty($validated['client_uuid'])) {
            $existing = Referral::where('client_uuid', $validated['client_uuid'])->first();
            if ($existing) {
                return new ReferralResource($existing);
            }
        }

        $encounter = Encounter::findOrFail($validated['encounter_id']);

        $referral = Referral::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $encounter->update(['current_department' => $validated['to_department']]);

        return response()->json(new ReferralResource($referral), 201);
    }
}
