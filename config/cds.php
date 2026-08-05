<?php

return [
    /* Pediatric dosing hints for CDS (string hints shown to clinicians) */
    'pediatric_max_doses' => [
        'paracetamol' => '15mg/kg/dose (max 60mg/kg/day)',
        'amoxicillin' => '25-50mg/kg/day divided',
    ],

    /* Drugs that may need renal adjustment */
    'renal_adjust_drugs' => ['metformin', 'gentamicin', 'nsaids', 'ibuprofen'],

    /* Drugs that have pregnancy-related cautions */
    'pregnancy_caution_drugs' => ['warfarin', 'ace inhibitors', 'lisinopril', 'isotretinoin', 'methotrexate'],

    /* Early warning score thresholds and scoring rules.
       These defaults mirror the existing hard-coded values in ClinicalDecisionSupport.
       Making them configurable allows site-specific tuning without code changes. */
    'ews' => [
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
    ],
];
