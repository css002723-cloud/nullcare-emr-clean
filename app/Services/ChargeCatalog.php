<?php

namespace App\Services;

/**
 * Placeholder MWK price list used to auto-price pending charges pulled
 * from lab orders, imaging orders, prescriptions, and dialysis sessions.
 *
 * IMPORTANT: these are illustrative demo prices, not the hospital's real
 * fee schedule. A billing officer can always edit the amount before saving
 * the invoice. Before real deployment this should move to a `fee_schedule`
 * table managed by billing admins, not a code constant.
 */
class ChargeCatalog
{
    private const LAB_PRICES = [
        'FBC' => 6000,
        'MALARIA_RDT' => 3000,
        'HIV_TEST' => 2500,
        'RENAL_PROFILE' => 12000,
        'LIVER_PROFILE' => 12000,
        'BLOOD_GLUCOSE' => 3500,
        'URINALYSIS' => 4000,
        'HB' => 3000,
        'CROSSMATCH' => 8000,
        'COVID_PCR' => 15000,
    ];

    private const LAB_DEFAULT = 5000;

    private const IMAGING_PRICES = [
        'X-RAY' => 8000,
        'ULTRASOUND' => 10000,
        'CT' => 45000,
        'MRI' => 60000,
        'FLUOROSCOPY' => 20000,
        'MAMMOGRAPHY' => 15000,
        'ECHOCARDIOGRAPHY' => 20000,
    ];

    private const IMAGING_DEFAULT = 10000;

    private const PRESCRIPTION_DEFAULT = 1500;

    private const DIALYSIS_SESSION_PRICE = 35000;

    public static function labOrderPrice(?string $testCode): float
    {
        return (float) (self::LAB_PRICES[strtoupper((string) $testCode)] ?? self::LAB_DEFAULT);
    }

    public static function imagingOrderPrice(?string $modality): float
    {
        return (float) (self::IMAGING_PRICES[strtoupper((string) $modality)] ?? self::IMAGING_DEFAULT);
    }

    public static function prescriptionPrice(): float
    {
        return (float) self::PRESCRIPTION_DEFAULT;
    }

    public static function dialysisSessionPrice(): float
    {
        return (float) self::DIALYSIS_SESSION_PRICE;
    }
}
