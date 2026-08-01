<?php

namespace Database\Seeders;

use App\Models\Allergy;
use App\Models\ClinicalNote;
use App\Models\DialysisSession;
use App\Models\Encounter;
use App\Models\FluidBalance;
use App\Models\ICUNote;
use App\Models\ImagingOrder;
use App\Models\ImagingReport;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\MedicationAdministration;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\User;
use App\Services\IdGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Builds one hand-crafted patient per major workflow stage/role (reception,
 * triage, doctor, lab, imaging, pharmacy, inpatient/ICU, dialysis, billing,
 * referrals, discharge, death, records merge, edge-case demographics) plus
 * a batch of plain random patients for list/search/pagination testing.
 *
 * Every scenario is independent and safe to re-run: it always creates
 * fresh rows rather than upserting, so re-seeding just adds another batch.
 * If you want a clean slate first, run `php artisan migrate:fresh --seed`.
 */
class DemoClinicalSeeder extends Seeder
{
    private User $reception;
    private User $nurse;
    private User $doctor;
    private User $labTech;
    private User $radiologist;
    private User $pharmacist;
    private User $billing;
    private User $dialysisTech;
    private User $records;

    public function run(): void
    {
        $this->reception = $this->actor('reception1', 'reception');
        $this->nurse = $this->actor('nurse1', 'nurse');
        $this->doctor = $this->actor('doctor1', 'doctor');
        $this->labTech = $this->actor('labtech1', 'lab_tech');
        $this->radiologist = $this->actor('radiologist1', 'radiologist');
        $this->pharmacist = $this->actor('pharmacist1', 'pharmacist');
        $this->billing = $this->actor('billing1', 'billing');
        $this->dialysisTech = $this->actor('dialysis1', 'dialysis_tech');
        $this->records = $this->actor('records1', 'records_officer');

        $this->justRegistered();
        $this->triageNormal();
        $this->triageEmergency();
        $this->pediatricWaitingConsultation();
        $this->consultationWithSevereAllergy();
        $this->consultationSignedNote();
        $this->labOrderPending();
        $this->labOrderCollected();
        $this->labResultCritical();
        $this->labResultNormal();
        $this->imagingRequested();
        $this->imagingCriticalFinding();
        $this->imagingPregnancySafetyFlag();
        $this->prescriptionPending();
        $this->admittedWithDispensedMeds();
        $this->admittedIcuSepsisAlert();
        $this->dialysisPatient();
        $this->referralPending();
        $this->referralAccepted();
        $this->invoiceUnpaid();
        $this->invoicePartial();
        $this->invoicePaidCash();
        $this->dischargedPatient();
        $this->deceasedPatient();
        $this->duplicateRecordForMerge();
        $this->undocumentedRegistration();
        $this->elderlyWithMultipleAllergies();
        $this->bulkRandomPatients(25);

        echo "\n✅ Demo clinical data seeded — patients now cover reception, triage, ".
            "consultation, lab, imaging, pharmacy, inpatient/ICU, dialysis, billing, ".
            "referrals, discharge, death, and records-merge scenarios.\n";
    }

    // ---------------------------------------------------------------
    // Scenarios
    // ---------------------------------------------------------------

    /** Reception: just registered, hasn't been seen by triage yet. */
    private function justRegistered(): void
    {
        $patient = Patient::factory()->create(['registered_by' => $this->reception->id]);

        $this->encounter($patient, [
            'stage' => 'registered',
            'current_department' => 'Reception',
            'chief_complaint' => 'Headache and fever for 2 days',
            'registered_by' => $this->reception->id,
        ]);
    }

    /** Nursing/Triage: vitals recorded, all within normal range. */
    private function triageNormal(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'triage',
            'current_department' => 'Triage & Nursing',
            'chief_complaint' => 'Routine antenatal checkup',
            'registered_by' => $this->reception->id,
        ]);

        $this->vitals($encounter, [
            'temperature_c' => 36.8, 'blood_pressure_systolic' => 118, 'blood_pressure_diastolic' => 76,
            'pulse_rate' => 78, 'respiratory_rate' => 16, 'spo2' => 98, 'pain_score' => 0,
            'is_abnormal' => false,
        ]);
    }

    /** Nursing/Triage: emergency priority, abnormal/red-flag vitals. */
    private function triageEmergency(): void
    {
        $patient = Patient::factory()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'triage',
            'priority' => 'emergency',
            'is_emergency' => true,
            'current_department' => 'Triage & Nursing',
            'chief_complaint' => 'Road traffic accident — chest trauma, difficulty breathing',
            'registered_by' => $this->reception->id,
        ]);

        $this->vitals($encounter, [
            'temperature_c' => 39.4, 'blood_pressure_systolic' => 86, 'blood_pressure_diastolic' => 54,
            'pulse_rate' => 138, 'respiratory_rate' => 32, 'spo2' => 88, 'pain_score' => 9,
            'gcs' => 13, 'early_warning_score' => 9, 'is_abnormal' => true,
            'abnormal_flags' => 'Hypotension, tachycardia, hypoxia, tachypnea',
        ]);
    }

    /** Pediatrics: child with guardian, waiting to see the doctor. */
    private function pediatricWaitingConsultation(): void
    {
        $patient = Patient::factory()->child()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'waiting_consultation',
            'current_department' => 'Outpatient',
            'chief_complaint' => 'Persistent cough and poor feeding',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        $this->vitals($encounter, [
            'temperature_c' => 38.1, 'pulse_rate' => 120, 'respiratory_rate' => 34,
            'spo2' => 95, 'weight_kg' => 12.4, 'height_cm' => 84, 'is_abnormal' => false,
            'recorded_by' => $this->nurse->id,
        ]);
    }

    /** Doctor: mid-consultation, severe allergy on file, note still being drafted (unsigned). */
    private function consultationWithSevereAllergy(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'in_consultation',
            'current_department' => 'Outpatient',
            'chief_complaint' => 'Sore throat, requesting antibiotics',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        Allergy::create([
            'patient_id' => $patient->id,
            'substance' => 'Penicillin',
            'reaction' => 'Anaphylaxis',
            'severity' => 'severe',
            'recorded_by' => $this->nurse->id,
        ]);

        ClinicalNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'note_type' => 'consultation',
            'presenting_complaint' => 'Sore throat for 3 days, difficulty swallowing',
            'examination_findings' => 'Tonsillar exudate, cervical lymphadenopathy',
            'author_id' => $this->doctor->id,
            'author_role' => 'doctor',
            'signed' => false,
        ]);
    }

    /** Doctor: full signed consultation note with diagnosis, ready to move to orders. */
    private function consultationSignedNote(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'awaiting_orders',
            'current_department' => 'Outpatient',
            'chief_complaint' => 'Excessive thirst and frequent urination',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        ClinicalNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'note_type' => 'consultation',
            'presenting_complaint' => 'Polyuria and polydipsia for 3 weeks, unintentional weight loss',
            'examination_findings' => 'BMI 22, no acute distress, mild dehydration',
            'diagnosis' => 'Suspected Type 2 Diabetes Mellitus',
            'icd_code' => 'E11.9',
            'plan' => 'Fasting blood glucose and renal profile, dietary counselling, review in 1 week',
            'author_id' => $this->doctor->id,
            'author_role' => 'doctor',
            'signed' => true,
            'signed_by_name' => $this->doctor->full_name,
            'signed_at' => now(),
        ]);
    }

    /** Lab: order just placed, nothing collected yet. */
    private function labOrderPending(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Follow-up: routine bloods requested');
        $this->labOrder($encounter, 'FBC', 'ordered');
    }

    /** Lab: specimen collected, in transit/awaiting processing. */
    private function labOrderCollected(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Suspected malaria, febrile illness');
        $labOrder = $this->labOrder($encounter, 'MALARIA_RDT', 'collected', priority: 'urgent');
        $labOrder->update(['collected_by' => $this->labTech->id, 'collected_at' => now()]);
    }

    /** Lab: verified result flagged critical and NOT yet acknowledged — tests the alert workflow. */
    private function labResultCritical(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Reduced urine output, generalised swelling');
        $labOrder = $this->labOrder($encounter, 'RENAL_PROFILE', 'verified');
        $labOrder->update(['collected_by' => $this->labTech->id, 'collected_at' => now(), 'received_at' => now()]);

        LabResult::create([
            'lab_order_id' => $labOrder->id,
            'result_value' => 'Creatinine 612',
            'unit' => 'umol/L',
            'reference_range' => '62-115',
            'is_critical' => true,
            'is_abnormal' => true,
            'interpretation' => 'Severely elevated — acute kidney injury likely, notify clinician immediately',
            'entered_by' => $this->labTech->id,
            'verified_by' => $this->labTech->id,
            'verified_at' => now(),
            'critical_alert_acknowledged' => false,
        ]);
    }

    /** Lab: verified, normal result — the everyday non-alert path. */
    private function labResultNormal(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Routine antenatal bloods');
        $labOrder = $this->labOrder($encounter, 'HB', 'verified');
        $labOrder->update(['collected_by' => $this->labTech->id, 'collected_at' => now(), 'received_at' => now()]);

        LabResult::create([
            'lab_order_id' => $labOrder->id,
            'result_value' => '13.2',
            'unit' => 'g/dL',
            'reference_range' => '12.0-15.5',
            'is_critical' => false,
            'is_abnormal' => false,
            'interpretation' => 'Within normal range',
            'entered_by' => $this->labTech->id,
            'verified_by' => $this->labTech->id,
            'verified_at' => now(),
        ]);
    }

    /** Imaging: study requested, not yet performed. */
    private function imagingRequested(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Persistent cough, suspected pneumonia');
        $this->imagingOrder($encounter, $patient, 'CR', 'Chest X-ray, PA view', 'Chest', 'requested');
    }

    /** Imaging: fully reported with a critical finding requiring clinician review. */
    private function imagingCriticalFinding(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Severe headache, sudden onset, worst of life');
        $order = $this->imagingOrder($encounter, $patient, 'CT', 'CT Head, non-contrast', 'Head', 'reported');

        ImagingReport::create([
            'imaging_order_id' => $order->id,
            'findings' => 'Acute hyperdensity within the subarachnoid space, predominantly basal cisterns',
            'impression' => 'Findings consistent with subarachnoid haemorrhage — urgent neurosurgical referral advised',
            'is_critical_finding' => true,
            'reported_by' => $this->radiologist->id,
            'reviewed_by_clinician' => $this->doctor->id,
            'reviewed_at' => now(),
        ]);
    }

    /** Imaging: female patient, ionizing modality, pregnancy not confirmed — exercises the safety flag. */
    private function imagingPregnancySafetyFlag(): void
    {
        $patient = Patient::factory()->adult()->create([
            'sex' => 'female',
            'registered_by' => $this->reception->id,
        ]);

        $encounter = $this->encounter($patient, [
            'stage' => 'awaiting_orders',
            'current_department' => 'Imaging',
            'chief_complaint' => 'Fall with wrist pain and swelling',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        $this->imagingOrder(
            $encounter, $patient, 'DX', 'Wrist X-ray', 'Left wrist', 'requested',
            isPregnancyChecked: false,
            safetyNotes: ' [SYSTEM FLAG: pregnancy status not confirmed prior to ionizing radiation study]'
        );
    }

    /** Pharmacy: prescription written, waiting to be dispensed. */
    private function prescriptionPending(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Bacterial skin infection');

        Prescription::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'drug_name' => 'Amoxicillin',
            'formulation' => 'Capsule',
            'dose' => '500mg',
            'route' => 'Oral',
            'frequency' => 'TDS',
            'duration' => '7 days',
            'status' => 'pending',
            'prescribed_by' => $this->doctor->id,
        ]);
    }

    /** Pharmacy + inpatient nursing: admitted patient, dispensed prescription with administration recorded. */
    private function admittedWithDispensedMeds(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'admitted',
            'current_department' => 'General Ward',
            'ward' => 'Male Ward',
            'bed' => 'MW-12',
            'chief_complaint' => 'Severe community-acquired pneumonia',
            'admission_diagnosis' => 'Community-acquired pneumonia',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        $prescription = Prescription::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'drug_name' => 'Ceftriaxone',
            'formulation' => 'Injection',
            'dose' => '1g',
            'route' => 'IV',
            'frequency' => 'BD',
            'duration' => '5 days',
            'status' => 'dispensed',
            'prescribed_by' => $this->doctor->id,
            'dispensed_by' => $this->pharmacist->id,
            'dispensed_at' => now()->subHours(6),
        ]);

        MedicationAdministration::create([
            'prescription_id' => $prescription->id,
            'administered_by' => $this->nurse->id,
            'administered_at' => now()->subHours(5),
            'dose_given' => '1g IV',
            'notes' => 'Tolerated well, no adverse reaction observed',
        ]);
        MedicationAdministration::create([
            'prescription_id' => $prescription->id,
            'administered_by' => $this->nurse->id,
            'administered_at' => now()->subHours(1),
            'dose_given' => '1g IV',
        ]);
    }

    /** ICU: admitted, mechanically ventilated, sepsis alert active, fluid balance being tracked. */
    private function admittedIcuSepsisAlert(): void
    {
        $patient = Patient::factory()->elderly()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'admitted',
            'current_department' => 'ICU',
            'ward' => 'ICU',
            'bed' => 'ICU-3',
            'is_emergency' => true,
            'priority' => 'emergency',
            'chief_complaint' => 'Septic shock secondary to urinary tract infection',
            'admission_diagnosis' => 'Septic shock',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        ICUNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $patient->id,
            'note_type' => 'daily_review',
            'ventilation_status' => 'Mechanical — AC mode',
            'oxygen_therapy' => 'FiO2 50%',
            'sedation_assessment' => 'RASS -2',
            'inotropes' => 'Noradrenaline 0.15 mcg/kg/min',
            'fluid_balance_summary' => 'Positive 800mL over last 24h',
            'sepsis_alert' => true,
            'body' => 'Patient remains haemodynamically unstable, on vasopressor support. Source control pending urology review.',
            'author_id' => $this->doctor->id,
            'author_role' => 'doctor',
        ]);

        FluidBalance::create([
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'direction' => 'in', 'category' => 'IV Fluids', 'volume_ml' => 1000,
            'notes' => 'Normal saline bolus', 'recorded_by' => $this->nurse->id, 'recorded_at' => now()->subHours(2),
        ]);
        FluidBalance::create([
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'direction' => 'out', 'category' => 'Urine output', 'volume_ml' => 220,
            'recorded_by' => $this->nurse->id, 'recorded_at' => now()->subHours(1),
        ]);
    }

    /** Dialysis: CKD patient with one completed and one scheduled session. */
    private function dialysisPatient(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'admitted',
            'current_department' => 'Dialysis Unit',
            'chief_complaint' => 'End-stage renal disease, routine haemodialysis',
            'admission_diagnosis' => 'CKD Stage 5',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        DialysisSession::create([
            'patient_id' => $patient->id, 'encounter_id' => $encounter->id,
            'ckd_stage' => '5', 'session_date' => now()->subDays(2),
            'pre_weight_kg' => 68.4, 'post_weight_kg' => 65.9, 'fluid_removal_target_l' => 2.5,
            'vascular_access_type' => 'AV Fistula', 'status' => 'completed',
            'performed_by' => $this->dialysisTech->id,
        ]);
        DialysisSession::create([
            'patient_id' => $patient->id, 'encounter_id' => $encounter->id,
            'ckd_stage' => '5', 'session_date' => now()->addDay(),
            'fluid_removal_target_l' => 2.5, 'vascular_access_type' => 'AV Fistula',
            'status' => 'scheduled',
        ]);
    }

    /** Referral: sent to another department, not yet actioned. */
    private function referralPending(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Suspected retinal detachment');

        Referral::create([
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'from_department' => 'Outpatient', 'to_department' => 'Ophthalmology',
            'reason' => 'Sudden painless vision loss, flashes and floaters — needs urgent ophthalmology review',
            'priority' => 'urgent', 'status' => 'pending',
            'referred_by' => $this->doctor->id, 'is_read' => false,
        ]);
    }

    /** Referral: accepted by the receiving department. */
    private function referralAccepted(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Complex wound requiring surgical debridement');

        Referral::create([
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'from_department' => 'Outpatient', 'to_department' => 'Surgery',
            'reason' => 'Infected diabetic foot ulcer, needs surgical assessment',
            'priority' => 'routine', 'status' => 'accepted',
            'referred_by' => $this->doctor->id, 'accepted_by' => $this->doctor->id, 'is_read' => true,
        ]);
    }

    /** Billing: invoice raised, nothing paid yet. */
    private function invoiceUnpaid(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Minor laceration, sutured');
        $this->invoiceWithLines($patient, $encounter, [
            ['service_category' => 'Consultation', 'description' => 'Outpatient consultation fee', 'amount' => 5000],
            ['service_category' => 'Procedure', 'description' => 'Wound suturing', 'amount' => 8000],
        ], amountPaid: 0, status: 'unpaid');
    }

    /** Billing: partially settled — some money in, balance still outstanding. */
    private function invoicePartial(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Admitted for observation, 2-night stay');
        $this->invoiceWithLines($patient, $encounter, [
            ['service_category' => 'Bed Fee', 'description' => 'Ward bed, 2 nights', 'amount' => 30000],
            ['service_category' => 'Laboratory', 'description' => 'Full blood count', 'amount' => 4500],
        ], amountPaid: 15000, status: 'partial');
    }

    /** Billing: fully settled cash invoice — the happy path. */
    private function invoicePaidCash(): void
    {
        [$patient, $encounter] = $this->outpatientAwaitingOrders('Routine outpatient visit');
        $invoice = $this->invoiceWithLines($patient, $encounter, [
            ['service_category' => 'Consultation', 'description' => 'Outpatient consultation fee', 'amount' => 5000],
        ], amountPaid: 5000, status: 'paid');
        $invoice->update(['payment_reference' => 'CASH-'.now()->format('ymd').'-001']);
    }

    /** Discharge: encounter fully closed out with an outcome recorded. */
    private function dischargedPatient(): void
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $this->encounter($patient, [
            'stage' => 'discharged',
            'current_department' => 'General Ward',
            'chief_complaint' => 'Malaria, uncomplicated',
            'admission_diagnosis' => 'Uncomplicated malaria',
            'outcome' => 'improved',
            'disposition_notes' => 'Completed 3-day course of Artemether-Lumefantrine, afebrile for 48h, discharged home with advice to return if symptoms recur.',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
            'closed_at' => now()->subDay(),
        ]);
    }

    /** Records/mortality: deceased patient with a closed, deceased-stage encounter. */
    private function deceasedPatient(): void
    {
        $patient = Patient::factory()->elderly()->create([
            'is_deceased' => true,
            'date_of_death' => now()->subDays(3),
            'registered_by' => $this->reception->id,
        ]);

        $this->encounter($patient, [
            'stage' => 'deceased',
            'current_department' => 'General Ward',
            'chief_complaint' => 'Advanced heart failure, decompensated',
            'admission_diagnosis' => 'Decompensated congestive heart failure',
            'outcome' => 'deceased',
            'disposition_notes' => 'Patient passed away despite resuscitation efforts. Family informed, bereavement support offered.',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
            'closed_at' => now()->subDays(3),
        ]);
    }

    /** Records: an accidental duplicate registration, merged into the canonical record. */
    private function duplicateRecordForMerge(): void
    {
        $original = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        Patient::factory()->create([
            'given_name' => $original->given_name,
            'family_name' => $original->family_name,
            'sex' => $original->sex,
            'date_of_birth' => $original->date_of_birth,
            'district' => $original->district,
            'merged_into_patient_id' => $original->id,
            'registered_by' => $this->records->id,
        ]);
    }

    /** Reception/Records edge case: no national ID, no known DOB, estimated age only. */
    private function undocumentedRegistration(): void
    {
        $patient = Patient::factory()->undocumented()->create([
            'patient_category' => 'emergency',
            'registered_by' => $this->reception->id,
        ]);

        $this->encounter($patient, [
            'stage' => 'triage',
            'priority' => 'urgent',
            'current_department' => 'Triage & Nursing',
            'chief_complaint' => 'Found unconscious, brought in by bystanders, identity unknown',
            'registered_by' => $this->reception->id,
        ]);
    }

    /** Elderly patient with two allergies of differing severity — polypharmacy/CDS testing. */
    private function elderlyWithMultipleAllergies(): void
    {
        $patient = Patient::factory()->elderly()->create(['registered_by' => $this->reception->id]);

        Allergy::create([
            'patient_id' => $patient->id, 'substance' => 'Sulfonamides',
            'reaction' => 'Rash', 'severity' => 'moderate', 'recorded_by' => $this->nurse->id,
        ]);
        Allergy::create([
            'patient_id' => $patient->id, 'substance' => 'Aspirin',
            'reaction' => 'Bronchospasm', 'severity' => 'severe', 'recorded_by' => $this->nurse->id,
        ]);

        $this->encounter($patient, [
            'stage' => 'waiting_consultation',
            'current_department' => 'Outpatient',
            'chief_complaint' => 'Joint pain, multiple chronic conditions',
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);
    }

    /** Plain volume: random patients (mostly no encounter) for search/pagination testing. */
    private function bulkRandomPatients(int $count): void
    {
        Patient::factory()->count($count)->create();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function actor(string $username, string $fallbackRole): User
    {
        return User::where('username', $username)->first()
            ?? User::where('role', $fallbackRole)->first()
            ?? User::first();
    }

    private function encounter(Patient $patient, array $attributes): Encounter
    {
        return Encounter::create(array_merge([
            'encounter_number' => $this->uniqueValue(fn () => IdGenerator::encounterNumber(), 'encounters', 'encounter_number'),
            'mrn' => $this->uniqueValue(fn () => IdGenerator::mrn(), 'encounters', 'mrn'),
            'patient_id' => $patient->id,
            'visit_type' => 'outpatient',
        ], $attributes));
    }

    /**
     * IdGenerator's encounter/mrn formats only have a small random window
     * per second (e.g. ENC-<second>-<2 digits> = 90 possibilities), which
     * production traffic rarely exhausts but a seeder creating dozens of
     * rows in the same instant easily can. Retry with a fresh value until
     * we find one that isn't already taken.
     */
    private function uniqueValue(callable $generator, string $table, string $column): string
    {
        do {
            $value = $generator();
        } while (DB::table($table)->where($column, $value)->exists());

        return $value;
    }

    private function vitals(Encounter $encounter, array $attributes): void
    {
        $encounter->vitals()->create(array_merge([
            'patient_id' => $encounter->patient_id,
            'recorded_by' => $this->nurse->id,
        ], $attributes));
    }

    /** Shorthand for the common "outpatient, seen, orders about to be placed" starting point. */
    private function outpatientAwaitingOrders(string $chiefComplaint): array
    {
        $patient = Patient::factory()->adult()->create(['registered_by' => $this->reception->id]);

        $encounter = $this->encounter($patient, [
            'stage' => 'awaiting_orders',
            'current_department' => 'Outpatient',
            'chief_complaint' => $chiefComplaint,
            'assigned_clinician_id' => $this->doctor->id,
            'registered_by' => $this->reception->id,
        ]);

        return [$patient, $encounter];
    }

    private function labOrder(Encounter $encounter, string $testCode, string $status, string $priority = 'routine'): LabOrder
    {
        $catalog = LabOrder::CATALOG[$testCode] ?? [];

        $order = Order::create([
            'encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id,
            'order_type' => 'lab', 'details' => $testCode, 'priority' => $priority,
            'target_department' => 'laboratory', 'ordered_by' => $this->doctor->id,
        ]);

        return LabOrder::create([
            'order_id' => $order->id, 'encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id,
            'test_code' => $testCode,
            'loinc_code' => $catalog['loinc_code'] ?? null,
            'loinc_display' => $catalog['loinc_display'] ?? null,
            'barcode' => IdGenerator::barcode(),
            'status' => $status, 'priority' => $priority, 'ordered_by' => $this->doctor->id,
        ]);
    }

    private function imagingOrder(
        Encounter $encounter,
        Patient $patient,
        string $modality,
        string $studyDescription,
        string $bodySite,
        string $status,
        bool $isPregnancyChecked = true,
        string $safetyNotes = ''
    ): ImagingOrder {
        $order = Order::create([
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'order_type' => 'imaging', 'details' => $studyDescription, 'priority' => 'routine',
            'target_department' => 'imaging', 'ordered_by' => $this->doctor->id,
        ]);

        return ImagingOrder::create([
            'order_id' => $order->id, 'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'modality' => $modality, 'study_description' => $studyDescription, 'body_site' => $bodySite,
            'clinical_indication' => $encounter->chief_complaint,
            'accession_number' => 'ACC'.random_int(10000000, 99999999),
            'is_pregnancy_checked' => $isPregnancyChecked, 'safety_checklist_notes' => $safetyNotes,
            'status' => $status, 'ordered_by' => $this->doctor->id,
        ]);
    }

    private function invoiceWithLines(Patient $patient, Encounter $encounter, array $lines, float $amountPaid, string $status): Invoice
    {
        $total = array_sum(array_column($lines, 'amount'));

        $invoice = Invoice::create([
            'invoice_number' => $this->uniqueValue(fn () => IdGenerator::invoiceNumber(), 'invoices', 'invoice_number'),
            'encounter_id' => $encounter->id, 'patient_id' => $patient->id,
            'payer_type' => 'cash', 'total_amount' => $total, 'amount_paid' => $amountPaid,
            'status' => $status, 'created_by' => $this->billing->id,
        ]);

        foreach ($lines as $line) {
            InvoiceLineItem::create(array_merge(['invoice_id' => $invoice->id], $line));
        }

        return $invoice;
    }
}
