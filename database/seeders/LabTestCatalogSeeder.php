<?php

namespace Database\Seeders;

use App\Models\LabTestCatalog;
use Illuminate\Database\Seeder;

class LabTestCatalogSeeder extends Seeder
{
    /**
     * Common outpatient tests for a Malawian primary/teaching hospital
     * setting, LOINC-coded, powering the Laboratory quick-order dropdown.
     */
    public function run(): void
    {
        $tests = [
            ['test_code' => 'FBC', 'loinc_code' => '58410-2', 'loinc_display' => 'Full Blood Count', 'default_specimen_type' => 'venous blood'],
            ['test_code' => 'MALARIA_RDT', 'loinc_code' => '49711-5', 'loinc_display' => 'Malaria Rapid Diagnostic Test', 'default_specimen_type' => 'capillary blood'],
            ['test_code' => 'MALARIA_SMEAR', 'loinc_code' => '32700-7', 'loinc_display' => 'Malaria Blood Smear', 'default_specimen_type' => 'venous blood'],
            ['test_code' => 'HIV_TEST', 'loinc_code' => '75622-1', 'loinc_display' => 'HIV Rapid Test', 'default_specimen_type' => 'capillary blood'],
            ['test_code' => 'RBG', 'loinc_code' => '2339-0', 'loinc_display' => 'Random Blood Glucose', 'default_specimen_type' => 'capillary blood'],
            ['test_code' => 'URINALYSIS', 'loinc_code' => '24357-6', 'loinc_display' => 'Urinalysis', 'default_specimen_type' => 'urine'],
            ['test_code' => 'PREGNANCY_TEST', 'loinc_code' => '2106-3', 'loinc_display' => 'Urine Pregnancy Test', 'default_specimen_type' => 'urine'],
            ['test_code' => 'LFT', 'loinc_code' => '24325-3', 'loinc_display' => 'Liver Function Tests', 'default_specimen_type' => 'venous blood'],
            ['test_code' => 'RFT', 'loinc_code' => '24321-2', 'loinc_display' => 'Renal Function Tests', 'default_specimen_type' => 'venous blood'],
            ['test_code' => 'STOOL_MICROSCOPY', 'loinc_code' => '580-6', 'loinc_display' => 'Stool Microscopy (O&P)', 'default_specimen_type' => 'stool'],
            ['test_code' => 'SPUTUM_AFB', 'loinc_code' => '11599-1', 'loinc_display' => 'Sputum AFB (TB) Smear', 'default_specimen_type' => 'sputum'],
            ['test_code' => 'HB', 'loinc_code' => '718-7', 'loinc_display' => 'Hemoglobin', 'default_specimen_type' => 'capillary blood'],
        ];

        foreach ($tests as $test) {
            LabTestCatalog::updateOrCreate(['test_code' => $test['test_code']], $test);
        }
    }
}
