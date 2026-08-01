<?php

namespace App\Services;

/**
 * Ported directly from backend/app/utils.py — same formats, same
 * character sets, same lengths. Keeping these identical matters: any ID
 * printed on a physical card or referenced across systems should look and
 * behave the same regardless of which backend issued it.
 */
class IdGenerator
{
    /** Per-visit hospital number: NC-YY-XXXXX. Regenerated fresh every encounter. */
    public static function mrn(): string
    {
        $year = now()->format('y');
        $suffix = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        return "NC-{$year}-{$suffix}";
    }

    /**
     * Permanent, anonymous, cross-visit patient identifier. Plain
     * alphanumeric, no facility/year prefix — excludes look-alike
     * characters (0/O, 1/I) exactly like the reference.
     */
    public static function patientUid(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $result = '';
        for ($i = 0; $i < 9; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $result;
    }

    public static function encounterNumber(): string
    {
        $ts = now()->format('ymdHis');

        return "ENC-{$ts}-".random_int(10, 99);
    }

    public static function invoiceNumber(): string
    {
        $ts = now()->format('ymd');
        $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return "INV-{$ts}-{$suffix}";
    }

    public static function barcode(): string
    {
        $result = '';
        for ($i = 0; $i < 10; $i++) {
            $result .= random_int(0, 9);
        }

        return $result;
    }
}
