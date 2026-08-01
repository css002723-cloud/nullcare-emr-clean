<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalNote extends Model
{
    /**
     * Structured outpatient documentation templates — mirrors
     * CLINIC_TEMPLATES in the Python reference, used by the
     * /encounters/note-templates endpoint to guide (not force)
     * documentation for specific clinic types.
     */
    const TEMPLATES = [
        'general_outpatient' => [
            'label' => 'General Outpatient',
            'review_of_systems_prompts' => [
                'General: fever, weight loss, night sweats, fatigue',
                'Cardiovascular: chest pain, palpitations, orthopnea',
                'Respiratory: cough, shortness of breath, wheeze',
                'Gastrointestinal: nausea, vomiting, diarrhea, abdominal pain',
                'Genitourinary: dysuria, frequency, discharge',
                'Musculoskeletal: joint pain, swelling, limitation of movement',
                'Neurological: headache, dizziness, weakness, seizures',
                'Skin: rash, itching, wounds',
            ],
            'examination_prompts' => ['General appearance', 'Vital signs review', 'Relevant system examination'],
        ],
        'antenatal' => [
            'label' => 'Antenatal Clinic (ANC)',
            'review_of_systems_prompts' => [
                'Fetal movement felt today?', 'Vaginal bleeding or discharge',
                'Swelling of face, hands, or feet', 'Headache or visual disturbance', 'Epigastric pain',
            ],
            'examination_prompts' => [
                'Fundal height (cm)', 'Fetal heart rate', 'Fetal lie and presentation',
                'Blood pressure', 'Urine dipstick (protein/glucose)', 'Pallor / edema',
            ],
        ],
        'under_five' => [
            'label' => 'Under-5 / Pediatric Clinic',
            'review_of_systems_prompts' => [
                'Feeding / breastfeeding history', 'Immunization status up to date?',
                'IMCI danger signs: unable to drink/breastfeed, vomits everything, convulsions, lethargic/unconscious',
                'Cough or difficulty breathing', 'Diarrhea — duration and blood in stool',
            ],
            'examination_prompts' => [
                'Weight-for-age', 'MUAC (mid-upper arm circumference)', 'Temperature',
                'Respiratory rate', 'Signs of dehydration', 'Pallor',
            ],
        ],
        'ncd' => [
            'label' => 'NCD Clinic (Hypertension / Diabetes)',
            'review_of_systems_prompts' => [
                'Chest pain or breathlessness on exertion', 'Polyuria, polydipsia, unexplained weight loss',
                'Visual changes', 'Numbness, tingling, or non-healing foot wounds',
                'Medication adherence since last visit',
            ],
            'examination_prompts' => [
                'Blood pressure (repeat if elevated)', 'Random/fasting blood glucose',
                'Weight and BMI', 'Foot examination (diabetic patients)', 'Fundoscopy if due',
            ],
        ],
        'hiv' => [
            'label' => 'HIV Clinic',
            'review_of_systems_prompts' => [
                'Adherence to ART since last visit',
                'Opportunistic infection symptoms: fever, cough, diarrhea, oral thrush',
                'Unintentional weight loss', 'New skin lesions or lymphadenopathy',
            ],
            'examination_prompts' => [
                'Weight', 'WHO clinical staging signs', 'Oral cavity examination',
                'Adherence count (pills remaining vs. expected)',
            ],
        ],
    ];

    protected $fillable = [
        'client_uuid', 'encounter_id', 'patient_id', 'note_type', 'clinic_template',
        'presenting_complaint', 'history_of_presenting_illness', 'past_medical_history',
        'past_surgical_history', 'medication_history', 'allergy_history', 'social_history',
        'family_history', 'review_of_systems', 'examination_findings', 'diagnosis',
        'icd_code', 'differential_diagnosis', 'plan', 'follow_up_plan', 'body', 'history',
        'author_id', 'author_role', 'signed', 'signed_by_name', 'signed_at', 'synced',
    ];

    protected function casts(): array
    {
        return ['signed' => 'boolean', 'synced' => 'boolean', 'signed_at' => 'datetime'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
