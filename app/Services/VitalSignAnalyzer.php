<?php

namespace App\Services;

use App\Models\VitalSign;

class VitalSignAnalyzer
{
    /**
     * Simplified NEWS2-style Early Warning Score: each vital sign contributes
     * 0-3 points based on how far it deviates from normal range; the total
     * gives a single number a nurse/doctor can act on at a glance.
     * This is a Phase 1 prototype scoring — not a substitute for a full
     * validated clinical scoring tool in a later phase.
     */
    public function earlyWarningScore(VitalSign $vital): int
    {
        return $this->temperatureScore($vital->temperature)
            + $this->respiratoryScore($vital->respiratory_rate)
            + $this->spo2Score($vital->oxygen_saturation)
            + $this->systolicScore($vital->blood_pressure_systolic)
            + $this->pulseScore($vital->pulse_rate);
    }

    /**
     * A score of 5+ is the standard NEWS2 threshold for "requires urgent
     * clinical review" — used here as the abnormal flag trigger.
     */
    public function isAbnormal(VitalSign $vital): bool
    {
        return $this->earlyWarningScore($vital) >= 5;
    }

    private function temperatureScore(?float $c): int
    {
        if ($c === null) return 0;
        if ($c < 35.0) return 3;
        if ($c <= 36.0) return 1;
        if ($c <= 38.0) return 0;
        if ($c <= 39.0) return 1;
        return 2;
    }

    private function respiratoryScore(?int $rr): int
    {
        if ($rr === null) return 0;
        if ($rr < 8) return 3;
        if ($rr <= 11) return 1;
        if ($rr <= 20) return 0;
        if ($rr <= 24) return 2;
        return 3;
    }

    private function spo2Score(?int $spo2): int
    {
        if ($spo2 === null) return 0;
        if ($spo2 <= 91) return 3;
        if ($spo2 <= 93) return 2;
        if ($spo2 <= 95) return 1;
        return 0;
    }

    private function systolicScore(?int $sbp): int
    {
        if ($sbp === null) return 0;
        if ($sbp <= 90) return 3;
        if ($sbp <= 100) return 2;
        if ($sbp <= 110) return 1;
        if ($sbp <= 219) return 0;
        return 3;
    }

    private function pulseScore(?int $hr): int
    {
        if ($hr === null) return 0;
        if ($hr <= 40) return 3;
        if ($hr <= 50) return 1;
        if ($hr <= 90) return 0;
        if ($hr <= 110) return 1;
        if ($hr <= 130) return 2;
        return 3;
    }
}
