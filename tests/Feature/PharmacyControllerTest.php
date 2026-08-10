<?php

use App\Http\Controllers\Api\PharmacyController;
use App\Models\Allergy;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
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
    expect($payload[0]['patient_uid'])->toBe('P12345678');
    expect($payload[0]['patient_allergies'])->toBe([]);
});

it('includes patient UID and allergy data on pharmacy prescriptions', function () {
    $patient = Patient::create([
        'patient_uid' => 'P12399999',
        'given_name' => 'Moses',
        'family_name' => 'Chirwa',
        'sex' => 'male',
    ]);

    Allergy::create([
        'patient_id' => $patient->id,
        'substance' => 'Penicillin',
        'reaction' => 'rash',
        'severity' => 'moderate',
    ]);

    Prescription::create([
        'encounter_id' => 1,
        'patient_id' => $patient->id,
        'drug_name' => 'Amoxicillin',
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

    expect($payload[0]['patient_uid'])->toBe('P12399999');
    expect($payload[0]['patient_allergies'][0])->toBe([ 
        'substance' => 'Penicillin',
        'reaction' => 'rash',
        'severity' => 'moderate',
    ]);
});

function makePendingPrescriptionWithAllergyAlert(): Prescription
{
    $patient = Patient::create([
        'patient_uid' => 'P00000001',
        'given_name' => 'Grace',
        'family_name' => 'Banda',
        'sex' => 'female',
    ]);

    $encounter = Encounter::create([
        'encounter_number' => 'ENC-0001',
        'mrn' => 'MRN-0001',
        'patient_id' => $patient->id,
    ]);

    return Prescription::create([
        'encounter_id' => $encounter->id,
        'patient_id' => $patient->id,
        'drug_name' => 'Amoxicillin',
        'status' => 'pending',
        'cds_alerts' => json_encode([
            'ALLERGY ALERT: patient has documented allergy to Amoxicillin (severe). Reaction: anaphylaxis.',
        ]),
    ]);
}

function makePharmacistRequest(array $input = []): Request
{
    $pharmacist = User::create([
        'first_name' => 'Peter',
        'last_name' => 'Phiri',
        'username' => 'pphiri',
        'email' => 'pphiri@example.test',
        'password' => 'password',
        'role' => 'pharmacist',
    ]);

    $request = new Request($input);
    $request->setUserResolver(fn () => $pharmacist);

    return $request;
}

it('blocks dispensing without confirmation when the prescription has an active allergy alert', function () {
    $prescription = makePendingPrescriptionWithAllergyAlert();
    $controller = app(PharmacyController::class);

    $response = $controller->dispense(makePharmacistRequest(), $prescription);
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(422)
        ->and($payload['requires_allergy_override'])->toBeTrue()
        ->and($prescription->fresh()->status)->toBe('pending');
});

it('allows dispensing an allergy-flagged prescription once explicitly overridden, and records who did it', function () {
    $prescription = makePendingPrescriptionWithAllergyAlert();
    $controller = app(PharmacyController::class);

    $response = $controller->dispense(makePharmacistRequest(['confirm_allergy_override' => true]), $prescription);
    $payload = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200)
        ->and($payload['status'])->toBe('dispensed')
        ->and($payload['allergy_override_warning'])->not->toBeNull();

    $fresh = $prescription->fresh();
    expect($fresh->status)->toBe('dispensed')
        ->and($fresh->allergy_override_by)->not->toBeNull()
        ->and($fresh->allergy_override_at)->not->toBeNull();
});
