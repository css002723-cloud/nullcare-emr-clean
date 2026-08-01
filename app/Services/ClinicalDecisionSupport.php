<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

/**
 * Ported directly from backend/app/utils.py's check_prescription_safety()
 * and compute_early_warning_score() — same rule set, same thresholds,
 * same alert wording. This is a prototype rule engine on both sides of
 * the merge; a production system would call a maintained drug
 * interaction/allergy database instead.
 */
class ClinicalDecisionSupport
{
    private const PEDIATRIC_MAX_DOSES = [
        'paracetamol' => '15mg/kg/dose (max 60mg/kg/day)',
        'amoxicillin' => '25-50mg/kg/day divided',
    ];

    private const RENAL_ADJUST_DRUGS = ['metformin', 'gentamicin', 'nsaids', 'ibuprofen'];
    private const PREGNANCY_CAUTION_DRUGS = ['warfarin', 'ace inhibitors', 'lisinopril', 'isotretinoin', 'methotrexate'];

    /**
     * @param  Collection  $activeAllergies  Allergy models for this patient
     * @param  Collection  $currentPrescriptions  Prescription models for this encounter
     * @return string[] CDS alert strings
     */
    public function checkPrescriptionSafety(string $drugName, Patient $patient, Collection $activeAllergies, Collection $currentPrescriptions): array
    {
        $alerts = [];
        $drugLower = strtolower($drugName);

        foreach ($activeAllergies as $allergy) {
            $substanceLower = strtolower($allergy->substance);
            if (str_contains($drugLower, $substanceLower) || str_contains($substanceLower, $drugLower)) {
                $severity = $allergy->severity ?: 'severity unknown';
                $reaction = $allergy->reaction ?: 'not recorded';
                $alerts[] = "ALLERGY ALERT: patient has documented allergy to {$allergy->substance} ({$severity}). Reaction: {$reaction}.";
            }
        }

        foreach ($currentPrescriptions as $rx) {
            if (strtolower($rx->drug_name) === $drugLower && $rx->status !== 'cancelled') {
                $alerts[] = "DUPLICATE THERAPY: {$drugName} already prescribed in this encounter.";
            }
        }

        if ($patient->isPediatric()) {
            $hint = self::PEDIATRIC_MAX_DOSES[$drugLower] ?? null;
            $alerts[] = 'PEDIATRIC DOSING: verify weight-based dose.'.($hint ? " Reference: {$hint}" : '');
        }

        foreach (self::RENAL_ADJUST_DRUGS as $renalDrug) {
            if (str_contains($drugLower, $renalDrug)) {
                $alerts[] = "RENAL CAUTION: {$drugName} may require dose adjustment in renal impairment — check renal profile.";
                break;
            }
        }

        if ($patient->sex === 'female') {
            foreach (self::PREGNANCY_CAUTION_DRUGS as $cautionDrug) {
                if (str_contains($drugLower, $cautionDrug)) {
                    $alerts[] = "PREGNANCY CAUTION: {$drugName} carries pregnancy-related risk — confirm pregnancy status.";
                    break;
                }
            }
        }

        return $alerts;
    }

    /**
     * @param  array  $vitals  keys: respiratory_rate, spo2, pulse_rate,
     *                         blood_pressure_systolic, temperature_c, gcs
     * @return array{0: int, 1: string[]} [score, flags]
     */
    public function computeEarlyWarningScore(array $vitals): array
    {
        $score = 0;
        $flags = [];

        if (($rr = $vitals['respiratory_rate'] ?? null) !== null) {
            if ($rr <= 8 || $rr >= 25) {
                $score += 3;
                $flags[] = 'Respiratory rate critical';
            } elseif ($rr >= 21) {
                $score += 2;
                $flags[] = 'Respiratory rate high';
            }
        }

        if (($spo2 = $vitals['spo2'] ?? null) !== null) {
            if ($spo2 < 92) {
                $score += 3;
                $flags[] = 'Oxygen saturation critically low';
            } elseif ($spo2 < 94) {
                $score += 1;
                $flags[] = 'Oxygen saturation low';
            }
        }

        if (($hr = $vitals['pulse_rate'] ?? null) !== null) {
            if ($hr <= 40 || $hr >= 131) {
                $score += 3;
                $flags[] = 'Heart rate critical';
            } elseif ($hr >= 111) {
                $score += 2;
                $flags[] = 'Heart rate high';
            }
        }

        if (($sbp = $vitals['blood_pressure_systolic'] ?? null) !== null) {
            if ($sbp <= 90) {
                $score += 3;
                $flags[] = 'Systolic BP critically low';
            } elseif ($sbp >= 220) {
                $score += 3;
                $flags[] = 'Systolic BP critically high';
            }
        }

        if (($temp = $vitals['temperature_c'] ?? null) !== null) {
            if ($temp <= 35.0 || $temp >= 39.1) {
                $score += 2;
                $flags[] = 'Temperature abnormal';
            }
        }

        if (($gcs = $vitals['gcs'] ?? null) !== null && $gcs < 15) {
            $score += 3;
            $flags[] = 'Reduced consciousness (GCS < 15)';
        }

        return [$score, $flags];
    }
}
