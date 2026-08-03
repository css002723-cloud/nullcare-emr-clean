<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #1B2B29; }
    .teal { color: #0F4C4A; }
    .muted { color: #6E7674; }
    h1 { font-size: 20pt; color: #0F4C4A; margin-bottom: 2px; }
    .subtitle { color: #6E7674; font-size: 11pt; margin: 0 0 2px 0; }
    .section-title { font-size: 13pt; font-weight: bold; color: #0F4C4A; border-bottom: 2px solid #0F4C4A; padding-bottom: 4px; margin-top: 18px; }
    .subsection-title { font-size: 10.5pt; font-weight: bold; color: #0F4C4A; margin-top: 10px; }
    .field { margin: 3px 0; }
    .field-label { font-weight: bold; color: #6E7674; display: inline-block; width: 150px; }
    .encounter-header { font-size: 11pt; font-weight: bold; color: #0F4C4A; margin-top: 14px; }
    .divider { border-top: 1px solid #DDD8CC; margin: 10px 0; }
    .body-line { margin: 2px 0; }
    .signed { font-style: italic; color: #6E7674; font-size: 8pt; }
    .emergency-tag { color: #C8443C; font-weight: bold; }
</style>
</head>
<body>
    <h1>NullCare</h1>
    <p class="subtitle">Full Patient Record</p>
    <p class="muted">Generated {{ $generatedAt }} by {{ $generatedByName }}</p>

    <div class="section-title">Patient Demographics</div>
    <div class="field"><span class="field-label">Patient ID (permanent):</span> {{ $patient->patient_uid }}</div>
    <div class="field"><span class="field-label">Full name:</span> {{ $patient->given_name }} {{ $patient->family_name }}</div>
    @if($patient->sex)<div class="field"><span class="field-label">Sex:</span> {{ $patient->sex }}</div>@endif
    @if($patient->date_of_birth)<div class="field"><span class="field-label">Date of birth:</span> {{ $patient->date_of_birth->toDateString() }}</div>@endif
    @if($age !== null)<div class="field"><span class="field-label">Age:</span> {{ $age }} years</div>@endif
    @if($patient->national_id)<div class="field"><span class="field-label">National ID:</span> {{ $patient->national_id }}</div>@endif
    @if($patient->phone)<div class="field"><span class="field-label">Phone:</span> {{ $patient->phone }}</div>@endif
    @if($patient->village || $patient->traditional_authority)<div class="field"><span class="field-label">Village / T.A.:</span> {{ implode(', ', array_filter([$patient->village, $patient->traditional_authority])) }}</div>@endif
    @if($patient->district || $patient->region)<div class="field"><span class="field-label">District / Region:</span> {{ implode(', ', array_filter([$patient->district, $patient->region])) }}</div>@endif
    @if($patient->occupation)<div class="field"><span class="field-label">Occupation:</span> {{ $patient->occupation }}</div>@endif
    @if($patient->guardian_name)<div class="field"><span class="field-label">Guardian:</span> {{ $patient->guardian_name }} ({{ $patient->guardian_relationship }}) -- {{ $patient->guardian_phone }}</div>@endif
    <div class="field"><span class="field-label">Patient category:</span> {{ $patient->patient_category }}</div>
    <div class="field"><span class="field-label">Consent -- research:</span> {{ $patient->consent_research ? 'Given' : 'Not given' }}</div>
    <div class="field"><span class="field-label">Consent -- teaching:</span> {{ $patient->consent_teaching ? 'Given' : 'Not given' }}</div>
    @if($patient->is_deceased)<div class="field"><span class="field-label">Deceased:</span> {{ $patient->date_of_death?->toDateString() ?? 'Yes' }}</div>@endif

    <div class="subsection-title">Allergies</div>
    @forelse($allergies as $a)
        <div class="body-line">- {{ $a->substance }} ({{ $a->severity ?: 'severity unknown' }}): {{ $a->reaction ?: 'reaction not recorded' }}</div>
    @empty
        <div class="body-line">No known allergies recorded.</div>
    @endforelse

    <div class="section-title">Visit History ({{ $encounters->count() }} encounter{{ $encounters->count() !== 1 ? 's' : '' }})</div>
    @forelse($encounters as $enc)
        <div class="encounter-header">
            Visit MRN {{ $enc->mrn }} -- {{ $enc->created_at?->format('Y-m-d H:i') }}
            @if($enc->is_emergency)<span class="emergency-tag"> [EMERGENCY]</span>@endif
        </div>
        <div class="field"><span class="field-label">Visit type / priority:</span> {{ $enc->visit_type }} / {{ $enc->priority }}</div>
        @if($enc->chief_complaint)<div class="field"><span class="field-label">Chief complaint:</span> {{ $enc->chief_complaint }}</div>@endif
        <div class="field"><span class="field-label">Stage / outcome:</span> {{ $enc->stage }}{{ $enc->outcome ? ' -> '.$enc->outcome : '' }}</div>
        @if($enc->ward)<div class="field"><span class="field-label">Ward / bed:</span> {{ $enc->ward }} / {{ $enc->bed ?: '-' }}</div>@endif
        @if($enc->admission_diagnosis)<div class="field"><span class="field-label">Admission diagnosis:</span> {{ $enc->admission_diagnosis }}</div>@endif
        @if($enc->disposition_notes)<div class="field"><span class="field-label">Disposition notes:</span> {{ $enc->disposition_notes }}</div>@endif

        @if($enc->vitals->isNotEmpty())
            <div class="subsection-title">Vital signs ({{ $enc->vitals->count() }} reading{{ $enc->vitals->count() !== 1 ? 's' : '' }})</div>
            @foreach($enc->vitals as $v)
                <div class="body-line">- {{ $v->created_at?->format('Y-m-d H:i') }}:
                    @php $parts = []; @endphp
                    @if($v->temperature_c !== null) @php $parts[] = "Temp {$v->temperature_c}C"; @endphp @endif
                    @if($v->blood_pressure_systolic) @php $parts[] = "BP {$v->blood_pressure_systolic}/{$v->blood_pressure_diastolic}"; @endphp @endif
                    @if($v->pulse_rate) @php $parts[] = "Pulse {$v->pulse_rate}"; @endphp @endif
                    @if($v->respiratory_rate) @php $parts[] = "RR {$v->respiratory_rate}"; @endphp @endif
                    @if($v->spo2) @php $parts[] = "SpO2 {$v->spo2}%"; @endphp @endif
                    @if($v->early_warning_score !== null) @php $parts[] = "EWS {$v->early_warning_score}"; @endphp @endif
                    {{ implode(', ', $parts) }}
                </div>
            @endforeach
        @endif

        @if($enc->clinicalNotes->isNotEmpty())
            <div class="subsection-title">Clinical notes ({{ $enc->clinicalNotes->count() }})</div>
            @foreach($enc->clinicalNotes as $n)
                <div class="body-line" style="font-weight:bold;">{{ ucwords(str_replace('_', ' ', $n->note_type)) }} -- {{ $n->created_at?->format('Y-m-d H:i') }}</div>
                @foreach([
                    'Presenting complaint' => $n->presenting_complaint, 'HPI' => $n->history_of_presenting_illness,
                    'PMH' => $n->past_medical_history, 'PSH' => $n->past_surgical_history,
                    'Medication hx' => $n->medication_history, 'Allergy hx' => $n->allergy_history,
                    'Social hx' => $n->social_history, 'Family hx' => $n->family_history,
                    'Review of systems' => $n->review_of_systems, 'Examination' => $n->examination_findings,
                    'Diagnosis' => $n->icd_code ? "{$n->diagnosis} ({$n->icd_code})" : $n->diagnosis,
                    'Differential' => $n->differential_diagnosis, 'Plan' => $n->plan,
                    'Follow-up plan' => $n->follow_up_plan, 'Notes' => $n->body,
                ] as $label => $value)
                    @if($value)<div class="field"><span class="field-label">{{ $label }}:</span> {{ $value }}</div>@endif
                @endforeach
                @if($n->signed && $n->signed_by_name)
                    <div class="signed">Signed by {{ $n->signed_by_name }} ({{ $n->author_role }}) on {{ $n->signed_at?->format('Y-m-d H:i') }}</div>
                @endif
            @endforeach
        @endif

        @if($enc->orders->isNotEmpty())
            <div class="subsection-title">Orders ({{ $enc->orders->count() }})</div>
            @foreach($enc->orders as $o)
                <div class="body-line">- {{ $o->order_type }}: {{ $o->details }} [{{ $o->status }}, {{ $o->priority }}]</div>
            @endforeach
        @endif

        @if($enc->labOrders->isNotEmpty())
            <div class="subsection-title">Laboratory ({{ $enc->labOrders->count() }})</div>
            @foreach($enc->labOrders as $lo)
                <div class="body-line">- {{ $lo->loinc_display ?: $lo->test_code }} [{{ $lo->status }}]
                    @if($lo->result)
                        : {{ $lo->result->result_value }} {{ $lo->result->unit }}
                        @if($lo->result->is_critical) (CRITICAL) @elseif($lo->result->is_abnormal) (abnormal) @endif
                    @endif
                </div>
            @endforeach
        @endif

        @if($enc->imagingOrders->isNotEmpty())
            <div class="subsection-title">Imaging ({{ $enc->imagingOrders->count() }})</div>
            @foreach($enc->imagingOrders as $io)
                <div class="body-line">- {{ $io->modality }} {{ $io->study_description }} [{{ $io->status }}]</div>
                @if($io->report)
                    @if($io->report->findings)<div class="field"><span class="field-label">&nbsp;&nbsp;Findings:</span> {{ $io->report->findings }}</div>@endif
                    @if($io->report->impression)<div class="field"><span class="field-label">&nbsp;&nbsp;Impression:</span> {{ $io->report->impression }}</div>@endif
                @endif
            @endforeach
        @endif

        @if($enc->prescriptions->isNotEmpty())
            <div class="subsection-title">Prescriptions ({{ $enc->prescriptions->count() }})</div>
            @foreach($enc->prescriptions as $rx)
                <div class="body-line">- {{ $rx->drug_name }} {{ $rx->formulation }} {{ $rx->dose }} {{ $rx->route }} {{ $rx->frequency }} [{{ $rx->status }}]</div>
                @if($rx->administrations->isNotEmpty())<div class="body-line">&nbsp;&nbsp;Administered {{ $rx->administrations->count() }}x</div>@endif
            @endforeach
        @endif

        @if($enc->referrals->isNotEmpty())
            <div class="subsection-title">Referrals / messages ({{ $enc->referrals->count() }})</div>
            @foreach($enc->referrals as $r)
                <div class="body-line">- {{ $r->from_department }} -> {{ $r->to_department }} [{{ $r->status }}]: {{ $r->reason }} (by {{ $r->referredBy?->full_name ?? 'unknown' }})</div>
            @endforeach
        @endif

        @if($enc->invoices->isNotEmpty())
            <div class="subsection-title">Billing ({{ $enc->invoices->count() }})</div>
            @foreach($enc->invoices as $inv)
                <div class="body-line">- Invoice {{ $inv->invoice_number }}: total {{ $inv->total_amount }}, paid {{ $inv->amount_paid }} [{{ $inv->status }}]</div>
            @endforeach
        @endif

        <div class="divider"></div>
    @empty
        <div class="body-line">No visits recorded.</div>
    @endforelse
</body>
</html>