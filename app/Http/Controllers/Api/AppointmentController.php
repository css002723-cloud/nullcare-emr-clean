<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdGenerator;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * GET /api/appointments?date=&status=&doctor_id=
     */
    public function index(Request $request)
    {
        $query = Appointment::with([
            'patient:id,given_name,family_name,patient_uid,phone',
            'doctor:id,first_name,last_name',
        ]);

        if ($request->filled('date')) {
            $query->whereDate('appointment_date', $request->query('date'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->query('doctor_id'));
        }

        $appointments = $query->orderBy('appointment_date')->orderBy('appointment_time')->get();

        return response()->json($this->appendFullNames($appointments));
    }

    /**
     * GET /api/appointments/doctors
     * Lightweight doctor list for the booking form's dropdown. Deliberately
     * separate from GET /users (admin-only) — reception and nurses need to
     * see doctor names to book against, not the full user-management view.
     */
    public function doctors()
    {
        $doctors = User::where('role', 'doctor')->where('is_active', true)
            ->select('id', 'first_name', 'last_name', 'department')
            ->get()
            ->each(fn ($d) => $d->append('full_name'))
            ->sortBy('full_name')
            ->values();

        return response()->json($doctors);
    }

    /**
     * POST /api/appointments
     * Roles: reception, nurse, admin
     * Body: { patient_id, doctor_id?, department?, appointment_date, appointment_time, reason?, priority? }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'nullable|exists:users,id',
            'department' => 'nullable|string|max:60',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'reason' => 'nullable|string',
            'priority' => 'nullable|in:routine,urgent,emergency',
            'contact_phone' => 'nullable|string|max:30',
        ]);

        do {
            $number = 'APT-'.now()->format('ymdHis').'-'.random_int(10, 99);
        } while (Appointment::where('appointment_number', $number)->exists());

        $appointment = Appointment::create(array_merge($validated, [
            'appointment_number' => $number,
            'department' => $validated['department'] ?? 'General Clinic',
            'priority' => $validated['priority'] ?? 'routine',
            'status' => 'scheduled',
            'booked_by' => $request->user()->id,
        ]));

        AuditLogger::log($request->user(), 'book_appointment', 'appointment', $appointment->id);

        $appointment->load([
            'patient:id,given_name,family_name,patient_uid,phone',
            'doctor:id,first_name,last_name',
        ]);
        $this->appendFullName($appointment);

        return response()->json($appointment, 201);
    }

    /**
     * PUT /api/appointments/{appointment}/status
     * Body: { status: scheduled|completed|missed|cancelled }
     * (checked_in is set only via checkIn() below, not through this endpoint,
     * since it must always come with a real linked encounter.)
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $status = $request->input('status');
        if (! in_array($status, ['scheduled', 'completed', 'missed', 'cancelled'], true)) {
            return response()->json(['message' => 'Invalid status.'], 422);
        }

        $appointment->update(['status' => $status]);
        AuditLogger::log($request->user(), 'update_appointment_status', 'appointment', $appointment->id, $status);

        $appointment->load([
            'patient:id,given_name,family_name,patient_uid,phone',
            'doctor:id,first_name,last_name',
        ]);
        $this->appendFullName($appointment);

        return response()->json($appointment);
    }

   
    public function checkIn(Request $request, Appointment $appointment)
    {
        if ($appointment->encounter_id) {
            return response()->json([
                'message' => 'This appointment has already been checked in.',
            ], 422);
        }

        $patient = Patient::findOrFail($appointment->patient_id);

        do {
            $mrn = IdGenerator::mrn();
        } while (Encounter::where('mrn', $mrn)->exists());

        $isEmergency = $appointment->priority === 'emergency';

        $encounter = Encounter::create([
            'encounter_number' => IdGenerator::encounterNumber(),
            'mrn' => $mrn,
            'patient_id' => $patient->id,
            'visit_type' => 'outpatient',
            'stage' => 'registered',
            'priority' => $appointment->priority,
            'is_emergency' => $isEmergency,
            'chief_complaint' => $appointment->reason,
            'current_department' => $isEmergency ? 'emergency' : 'triage',
            'assigned_clinician_id' => $appointment->doctor_id,
            'registered_by' => $request->user()->id,
        ]);

        $appointment->update([
            'status' => 'checked_in',
            'encounter_id' => $encounter->id,
            'checked_in_at' => now(),
        ]);

        AuditLogger::log(
            $request->user(), 'check_in_appointment', 'appointment', $appointment->id,
            "encounter_id={$encounter->id}"
        );

        return response()->json([
            'appointment' => $appointment->fresh(),
            'encounter' => $encounter,
        ], 201);
    }

    private function appendFullName(Appointment $appointment): void
    {
        $appointment->patient?->append('full_name');
        $appointment->doctor?->append('full_name');
    }

    private function appendFullNames($appointments)
    {
        return $appointments->each(fn ($a) => $this->appendFullName($a));
    }
}