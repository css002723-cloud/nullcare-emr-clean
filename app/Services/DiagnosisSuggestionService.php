<?php

namespace App\Services;

use App\Models\Encounter;

class DiagnosisSuggestionService
{
    /**
     * IMPORTANT: this is decision SUPPORT, not diagnosis. It returns a
     * ranked list of candidate diagnoses based on simple keyword/vitals
     * pattern-matching against common outpatient presentations in a
     * Malawian primary care setting. It NEVER writes to the encounter
     * record — a clinician reviews the list and explicitly chooses (or
     * ignores it and writes their own), via the existing
     * PATCH /encounters/{id} endpoint. Auto-filling a diagnosis without a
     * clinician's explicit judgment would be a genuine patient-safety
     * risk regardless of how good the matching rules are, so this
     * boundary is deliberate and should not be removed.
     */
    private array $rules = [
        [
            'icd10_code' => 'B54', 'label' => 'Malaria, unspecified',
            'keywords' => ['fever', 'chills', 'headache', 'sweating', 'rigors'],
            'min_matches' => 2,
        ],
        [
            'icd10_code' => 'J06.9', 'label' => 'Acute upper respiratory infection',
            'keywords' => ['cough', 'sore throat', 'runny nose', 'nasal congestion'],
            'min_matches' => 2,
        ],
        [
            'icd10_code' => 'J18.9', 'label' => 'Pneumonia, unspecified organism',
            'keywords' => ['cough', 'chest pain', 'difficulty breathing', 'shortness of breath'],
            'min_matches' => 2,
        ],
        [
            'icd10_code' => 'N39.0', 'label' => 'Urinary tract infection',
            'keywords' => ['dysuria', 'burning urination', 'frequent urination', 'lower abdominal pain'],
            'min_matches' => 2,
        ],
        [
            'icd10_code' => 'A09', 'label' => 'Gastroenteritis',
            'keywords' => ['diarrhea', 'vomiting', 'abdominal pain', 'nausea'],
            'min_matches' => 2,
        ],
        [
            'icd10_code' => 'I10', 'label' => 'Essential hypertension',
            'keywords' => ['headache', 'dizziness', 'blurred vision'],
            'min_matches' => 1,
            'requires_vital' => 'high_bp',
        ],
        [
            'icd10_code' => 'E11.9', 'label' => 'Type 2 diabetes mellitus',
            'keywords' => ['excessive thirst', 'frequent urination', 'fatigue', 'weight loss'],
            'min_matches' => 2,
        ],
    ];

    public function suggestFor(Encounter $encounter): array
    {
        $text = strtolower(($encounter->presenting_complaint ?? '').' '.($encounter->examination_findings ?? ''));
        $latestVital = $encounter->vitalSigns()->latest('recorded_at')->first();

        $suggestions = [];

        foreach ($this->rules as $rule) {
            $matchedKeywords = array_filter($rule['keywords'], fn ($kw) => str_contains($text, $kw));
            $matchCount = count($matchedKeywords);

            $vitalSatisfied = true;
            if (isset($rule['requires_vital']) && $rule['requires_vital'] === 'high_bp') {
                $vitalSatisfied = $latestVital && $latestVital->blood_pressure_systolic && $latestVital->blood_pressure_systolic >= 140;
            }

            if ($matchCount >= $rule['min_matches'] && $vitalSatisfied) {
                $suggestions[] = [
                    'icd10_code' => $rule['icd10_code'],
                    'label' => $rule['label'],
                    'matched_on' => array_values($matchedKeywords),
                    'confidence' => $matchCount >= $rule['min_matches'] + 1 ? 'moderate' : 'low',
                ];
            }
        }

        return $suggestions;
    }
}
