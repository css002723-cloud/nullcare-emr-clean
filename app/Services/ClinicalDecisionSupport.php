<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Collection;

/**
 * Clinical decision support service.
 *
 * This class now reads tunable lists and thresholds from config/cds.php so
 * behaviour can be adjusted per-site without changing code. Defaults are
 * kept to preserve current behaviour.
 */
class ClinicalDecisionSupport
{
    private function pediatricMaxDoses(): array
    {
        return config('cds.pediatric_max_doses', [
            'paracetamol' => '15mg/kg/dose (max 60mg/kg/day)',
            'amoxicillin' => '25-50mg/kg/day divided',
        ]);
    }

    private function renalAdjustDrugs(): array
    {
        return config('cds.renal_adjust_drugs', ['metformin', 'gentamicin', 'nsaids', 'ibuprofen']);
    }

    private function pregnancyCautionDrugs(): array
    {
        return config('cds.pregnancy_caution_drugs', ['warfarin', 'ace inhibitors', 'lisinopril', 'isotretinoin', 'methotrexate']);
    }

    /**
     * @param  Collection  $activeAllergies
     * @param  Collection  $currentPrescriptions
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
            $hint = $this->pediatricMaxDoses()[strtolower($drugName)] ?? null;
            $alerts[] = 'PEDIATRIC DOSING: verify weight-based dose.'.($hint ? " Reference: {$hint}" : '');
        }

        foreach ($this->renalAdjustDrugs() as $renalDrug) {
            if (str_contains($drugLower, $renalDrug)) {
                $alerts[] = "RENAL CAUTION: {$drugName} may require dose adjustment in renal impairment — check renal profile.";
                break;
            }
        }

        if ($patient->sex === 'female') {
            foreach ($this->pregnancyCautionDrugs() as $cautionDrug) {
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

        $ews = config('cds.ews', [
            'rr_critical_low' => 8,
            'rr_critical_high' => 25,
            'rr_high' => 21,

            'spo2_crit' => 92,
            'spo2_low' => 94,

            'hr_crit_low' => 40,
            'hr_crit_high' => 131,
            'hr_high' => 111,

            'sbp_crit_low' => 90,
            'sbp_crit_high' => 220,

            'temp_low' => 35.0,
            'temp_high' => 39.1,

            'gcs_normal' => 15,
        ]);

        if (($rr = $vitals['respiratory_rate'] ?? null) !== null) {
            if ($rr <= $ews['rr_critical_low'] || $rr >= $ews['rr_critical_high']) {
                $score += 3;
                $flags[] = 'Respiratory rate critical';
            } elseif ($rr >= $ews['rr_high']) {
                $score += 2;
                $flags[] = 'Respiratory rate high';
            }
        }

        if (($spo2 = $vitals['spo2'] ?? null) !== null) {
            if ($spo2 < $ews['spo2_crit']) {
                $score += 3;
                $flags[] = 'Oxygen saturation critically low';
            } elseif ($spo2 < $ews['spo2_low']) {
                $score += 1;
                $flags[] = 'Oxygen saturation low';
            }
        }

        if (($hr = $vitals['pulse_rate'] ?? null) !== null) {
            if ($hr <= $ews['hr_crit_low'] || $hr >= $ews['hr_crit_high']) {
                $score += 3;
                $flags[] = 'Heart rate critical';
            } elseif ($hr >= $ews['hr_high']) {
                $score += 2;
                $flags[] = 'Heart rate high';
            }
        }

        if (($sbp = $vitals['blood_pressure_systolic'] ?? null) !== null) {
            if ($sbp <= $ews['sbp_crit_low']) {
                $score += 3;
                $flags[] = 'Systolic BP critically low';
            } elseif ($sbp >= $ews['sbp_crit_high']) {
                $score += 3;
                $flags[] = 'Systolic BP critically high';
            }
        }

        if (($temp = $vitals['temperature_c'] ?? null) !== null) {
            if ($temp <= $ews['temp_low'] || $temp >= $ews['temp_high']) {
                $score += 2;
                $flags[] = 'Temperature abnormal';
            }
        }

        if (($gcs = $vitals['gcs'] ?? null) !== null && $gcs < $ews['gcs_normal']) {
            $score += 3;
            $flags[] = 'Reduced consciousness (GCS < 15)';
        }

        return [$score, $flags];
    }
}
