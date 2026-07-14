<?php

namespace App\Services;

use App\Models\VitalSign;

class VitalSignAnalyzer
{
    /**
     * Simple, transparent adult-vitals thresholds for the Phase 1 prototype.
     * Deliberately conservative (flags early) since this drives a visible
     * alert for the nurse/clinician, not an automated clinical decision.
     * Swap for age/context-aware rules in a later phase.
     */
    public function isAbnormal(VitalSign $vital): bool
    {
        return $this->tempAbnormal($vital->temperature)
            || $this->bpAbnormal($vital->blood_pressure_systolic, $vital->blood_pressure_diastolic)
            || $this->pulseAbnormal($vital->pulse_rate)
            || $this->respAbnormal($vital->respiratory_rate)
            || $this->spo2Abnormal($vital->oxygen_saturation)
            || $this->glucoseAbnormal($vital->blood_glucose);
    }

    private function tempAbnormal(?float $celsius): bool
    {
        return $celsius !== null && ($celsius < 35.0 || $celsius > 38.5);
    }

    private function bpAbnormal(?int $systolic, ?int $diastolic): bool
    {
        if ($systolic !== null && ($systolic < 90 || $systolic > 140)) {
            return true;
        }

        return $diastolic !== null && ($diastolic < 60 || $diastolic > 90);
    }

    private function pulseAbnormal(?int $bpm): bool
    {
        return $bpm !== null && ($bpm < 50 || $bpm > 120);
    }

    private function respAbnormal(?int $breathsPerMin): bool
    {
        return $breathsPerMin !== null && ($breathsPerMin < 10 || $breathsPerMin > 24);
    }

    private function spo2Abnormal(?int $percent): bool
    {
        return $percent !== null && $percent < 92;
    }

    private function glucoseAbnormal(?float $mmol): bool
    {
        return $mmol !== null && ($mmol < 3.5 || $mmol > 11.0);
    }
}
