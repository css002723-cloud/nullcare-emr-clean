<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

class AllergyChecker
{
    /**
     * Free-text match between a drug name and the patient's recorded
     * allergens. Deliberately loose (case-insensitive substring both ways)
     * since "Penicillin" should also catch "Amoxicillin" family entries
     * only if explicitly recorded that way — this is NOT a drug-interaction
     * database, just a guard against the exact/near-exact allergen on file.
     * A future phase should map both sides to a coded drug ontology.
     */
    public function conflictingAllergies(Patient $patient, string $drugName): Collection
    {
        $drug = strtolower($drugName);

        return $patient->allergies()
            ->get()
            ->filter(function ($allergy) use ($drug) {
                $allergen = strtolower($allergy->allergen);

                return str_contains($drug, $allergen) || str_contains($allergen, $drug);
            })
            ->values();
    }
}
