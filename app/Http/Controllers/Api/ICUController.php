<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use App\Models\ICUNote;
use App\Models\ImagingOrder;
use App\Models\ImagingReport;
use App\Models\LabOrder;
use App\Models\LabResult;
use App\Models\Patient;
use App\Models\Vital;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ICUController extends Controller
{
    private const ICU_WARD = 'ICU/HDU';

    private function latestEws(int $encounterId): ?int
    {
        return Vital::where('encounter_id', $encounterId)->latest()->first()?->early_warning_score;
    }

    /**
     * GET /api/icu/patients
     * Everyone currently in ICU/HDU, with what a critical care round needs at a glance.
     */
    public function listPatients()
    {
        $encounters = Encounter::where('stage', 'admitted')->where('ward', self::ICU_WARD)->latest()->get();

        $result = $encounters->map(function (Encounter $e) {
            $d = $e->toArray();
            $patient = Patient::find($e->patient_id);
            $d['patient'] = $patient?->toArray();
            $d['latest_ews'] = $this->latestEws($e->id);
            $latestNote = ICUNote::where('encounter_id', $e->id)->latest()->first();
            $d['latest_note'] = $latestNote;
            $d['sepsis_alert'] = (bool) ($latestNote?->sepsis_alert);

            return $d;
        });

        return response()->json($result);
    }

    /**
     * POST /api/icu/admit
     * Roles: doctor, nurse, admin
     * Admits straight into ICU/HDU and records the admission note in one step.
     */
    public function admit(Request $request)
    {
        if (! $request->filled('encounter_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id is required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));

        $encounter->update([
            'stage' => 'admitted',
            'visit_type' => 'inpatient',
            'ward' => self::ICU_WARD,
            'bed' => $request->input('bed'),
            'admission_diagnosis' => $request->input('admission_diagnosis'),
            'current_department' => 'ward',
        ]);

        $note = ICUNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'note_type' => 'admission',
            'ventilation_status' => $request->input('ventilation_status', 'none'),
            'oxygen_therapy' => $request->input('oxygen_therapy'),
            'sedation_assessment' => $request->input('sedation_assessment'),
            'inotropes' => $request->input('inotropes'),
            'sepsis_alert' => $request->input('sepsis_alert', false),
            'body' => $request->input('admission_note'),
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->role,
        ]);

        AuditLogger::log(
            $request->user(), 'icu_admit', 'encounter', $encounter->id,
            "bed={$request->input('bed')} sepsis_alert=".($note->sepsis_alert ? 'true' : 'false')
        );

        return response()->json($encounter);
    }

    /**
     * GET /api/icu/notes/{encounter}
     */
    public function listNotes(Encounter $encounter)
    {
        return response()->json(ICUNote::where('encounter_id', $encounter->id)->latest()->get());
    }

    /**
     * POST /api/icu/notes
     * Roles: doctor, nurse, admin
     */
    public function storeNote(Request $request)
    {
        if (! $request->filled('encounter_id')) {
            return response()->json(['error' => 'missing_fields', 'message' => 'encounter_id is required'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));

        $note = ICUNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'note_type' => $request->input('note_type', 'daily_review'),
            'ventilation_status' => $request->input('ventilation_status'),
            'oxygen_therapy' => $request->input('oxygen_therapy'),
            'sedation_assessment' => $request->input('sedation_assessment'),
            'inotropes' => $request->input('inotropes'),
            'fluid_balance_summary' => $request->input('fluid_balance_summary'),
            'sepsis_alert' => $request->input('sepsis_alert', false),
            'body' => $request->input('body'),
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->role,
            'client_uuid' => $request->input('client_uuid'),
        ]);

        AuditLogger::log($request->user(), 'create_icu_note', 'encounter', $encounter->id, $request->input('note_type'));

        return response()->json($note, 201);
    }

    /**
     * GET /api/icu/patient/{encounter}
     * Full critical care chart.
     */
    public function patientDetail(Encounter $encounter)
    {
        $patient = Patient::find($encounter->patient_id);

        $d = $encounter->toArray();
        $d['patient'] = $patient?->toArray();
        $d['referral'] = in_array($encounter->stage, Encounter::CLOSED_STAGES, true) ? null : $encounter->activeReferralSummary();
        $d['vitals'] = Vital::where('encounter_id', $encounter->id)->orderBy('created_at')->get();
        $d['notes'] = ICUNote::where('encounter_id', $encounter->id)->latest()->get();

        return response()->json($d);
    }

    /**
     * POST /api/icu/discharge
     * Roles: doctor, admin
     */
    public function discharge(Request $request)
    {
        $outcome = $request->input('outcome', 'discharged');
        if (! in_array($outcome, ['discharged', 'admitted', 'referred_out', 'died'], true)) {
            return response()->json(['error' => 'invalid_outcome'], 400);
        }

        $encounter = Encounter::findOrFail($request->input('encounter_id'));

        ICUNote::create([
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'note_type' => 'discharge_summary',
            'body' => $request->input('summary'),
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->role,
        ]);

        $encounter->outcome = $outcome;
        $encounter->disposition_notes = $request->input('summary');
        $encounter->stage = match ($outcome) {
            'died' => 'deceased',
            'admitted' => 'admitted',
            default => 'discharged',
        };
        $encounter->closed_at = now();

        if ($outcome === 'died') {
            Patient::where('id', $encounter->patient_id)->update(['is_deceased' => true, 'date_of_death' => now()]);
        }

        $encounter->save();

        AuditLogger::log($request->user(), 'icu_discharge', 'encounter', $encounter->id, $outcome);

        return response()->json($encounter);
    }

    /**
     * GET /api/icu/critical-alerts
     * Unacknowledged critical labs, critical imaging findings, and open
     * sepsis flags — scoped to patients currently in ICU/HDU.
     */
    public function criticalAlerts()
    {
        $icuEncounterIds = Encounter::where('stage', 'admitted')->where('ward', self::ICU_WARD)->pluck('id');

        if ($icuEncounterIds->isEmpty()) {
            return response()->json(['critical_labs' => [], 'critical_imaging' => [], 'sepsis_alerts' => []]);
        }

        $enrich = function (?int $encounterId) {
            $e = $encounterId ? Encounter::find($encounterId) : null;
            $p = $e ? Patient::find($e->patient_id) : null;

            return [
                'encounter_id' => $encounterId,
                'patient_name' => $p ? "{$p->given_name} {$p->family_name}" : null,
                'patient_uid' => $p?->patient_uid,
                'mrn' => $e?->mrn,
            ];
        };

        $criticalLabs = LabResult::whereHas('labOrder', fn ($q) => $q->whereIn('encounter_id', $icuEncounterIds))
            ->where('is_critical', true)->where('critical_alert_acknowledged', false)->get();

        $criticalImaging = ImagingReport::whereHas('imagingOrder', fn ($q) => $q->whereIn('encounter_id', $icuEncounterIds))
            ->where('is_critical_finding', true)->get();

        $sepsisNotes = ICUNote::whereIn('encounter_id', $icuEncounterIds)->where('sepsis_alert', true)->latest()->get();

        return response()->json([
            'critical_labs' => $criticalLabs->map(fn ($r) => [...$r->toArray(), ...$enrich($r->labOrder?->encounter_id)]),
            'critical_imaging' => $criticalImaging->map(fn ($r) => [...$r->toArray(), ...$enrich($r->imagingOrder?->encounter_id)]),
            'sepsis_alerts' => $sepsisNotes->map(fn ($n) => [...$n->toArray(), ...$enrich($n->encounter_id)]),
        ]);
    }

    /**
     * GET /api/icu/dashboard
     * Mortality/morbidity review: outcomes and length-of-stay for every
     * encounter ever admitted to ICU/HDU.
     */
    public function dashboard()
    {
        $icuEncounters = Encounter::where('ward', self::ICU_WARD)->get();
        $total = $icuEncounters->count();
        $closed = $icuEncounters->whereIn('stage', Encounter::CLOSED_STAGES);
        $died = $closed->where('outcome', 'died');
        $dischargedAlive = $closed->whereIn('outcome', ['discharged', 'admitted', 'referred_out']);

        $stays = $closed->filter(fn ($e) => $e->closed_at && $e->created_at)
            ->map(fn ($e) => $e->closed_at->diffInMinutes($e->created_at) / 60.0);

        $sepsisCount = $icuEncounters->isEmpty()
            ? 0
            : ICUNote::whereIn('encounter_id', $icuEncounters->pluck('id'))->where('sepsis_alert', true)->count();

        return response()->json([
            'total_icu_admissions' => $total,
            'currently_admitted' => $total - $closed->count(),
            'deaths' => $died->count(),
            'discharged_alive' => $dischargedAlive->count(),
            'mortality_rate_pct' => $closed->count() ? round($died->count() / $closed->count() * 100, 1) : 0,
            'avg_length_of_stay_hours' => $stays->count() ? round($stays->avg(), 1) : null,
            'sepsis_alert_count' => $sepsisCount,
        ]);
    }
}
