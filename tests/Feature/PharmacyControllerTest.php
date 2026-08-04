<?php

use App\Http\Controllers\Api\PharmacyController;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

it('includes the patient name on pharmacy prescriptions', function () {
    $patient = Patient::create([
        'patient_uid' => 'P12345678',
        'given_name' => 'Amina',
        'family_name' => 'Khan',
        'sex' => 'female',
    ]);

    Prescription::create([
        'encounter_id' => 1,
        'patient_id' => $patient->id,
        'drug_name' => 'Paracetamol',
        'formulation' => 'tablet',
        'dose' => '500mg',
        'route' => 'oral',
        'frequency' => 'bd',
        'duration' => '5 days',
        'status' => 'pending',
    ]);

    $controller = app(PharmacyController::class);
    $response = $controller->indexPrescriptions(new Request(['status' => 'pending']));

    $payload = json_decode($response->getContent(), true);

    expect($payload[0]['patient_name'])->toBe('Amina Khan');
});
