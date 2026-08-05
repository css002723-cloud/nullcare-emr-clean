import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  Clock3,
  LogIn,
  Phone,
  Stethoscope,
  UserRound,
} from "lucide-react";
import { Badge, Button, Card, Field, Input, Select, Textarea } from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";
import { useAuth } from "../context/AuthContext";
import api from "../services/api";

const initialForm = {
  patientId: null,
  patientLabel: "",
  doctorId: "",
  department: "General Clinic",
  date: "",
  time: "",
  reason: "",
  priority: "routine",
  contactPhone: "",
};

function formatDateTime(date, time) {
  if (!date || !time) return "Pending";
  const parsed = new Date(`${date}T${time}`);
  if (Number.isNaN(parsed.getTime())) return "Pending";
  return parsed.toLocaleString([], {
    weekday: "short", month: "short", day: "numeric", hour: "numeric", minute: "2-digit",
  });
}

function badgeTone(status) {
  if (status === "completed") return "success";
  if (status === "missed" || status === "cancelled") return "critical";
  if (status === "checked_in") return "success";
  return "warning";
}

function badgeLabel(status) {
  return { completed: "Completed", missed: "Missed", cancelled: "Cancelled", checked_in: "Checked in" }[status] || "Scheduled";
}

export default function Appointments() {
  const { hasRole } = useAuth();
  const navigate = useNavigate();
  const [appointments, setAppointments] = useState([]);
  const [doctors, setDoctors] = useState([]);
  const [form, setForm] = useState(initialForm);
  const [lookupKey, setLookupKey] = useState(0);
  const [filter, setFilter] = useState("all");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [busyId, setBusyId] = useState(null);

  const canBook = hasRole("reception", "nurse", "admin");

  useEffect(() => {
    loadAppointments();
    api.get("/appointments/doctors").then((res) => setDoctors(res.data)).catch(() => setDoctors([]));
  }, []);

  async function loadAppointments() {
    setLoading(true);
    try {
      const { data } = await api.get("/appointments");
      setAppointments(data);
    } catch {
      setError("Unable to load appointments right now.");
    } finally {
      setLoading(false);
    }
  }

  const stats = useMemo(() => {
    const scheduled = appointments.filter((a) => a.status === "scheduled").length;
    const missed = appointments.filter((a) => a.status === "missed").length;
    const completed = appointments.filter((a) => a.status === "completed").length;
    const checkedIn = appointments.filter((a) => a.status === "checked_in").length;
    return { total: appointments.length, scheduled, missed, completed, checkedIn };
  }, [appointments]);

  const visibleAppointments = useMemo(() => {
    if (filter === "all") return appointments;
    return appointments.filter((a) => a.status === filter);
  }, [appointments, filter]);

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setMessage(""); setError("");

    if (!form.patientId || !form.date || !form.time || !form.department) {
      setError("Select a patient, then complete the date, time, and department fields.");
      return;
    }

    try {
      const { data } = await api.post("/appointments", {
        patient_id: form.patientId,
        doctor_id: form.doctorId || null,
        department: form.department,
        appointment_date: form.date,
        appointment_time: form.time,
        reason: form.reason.trim(),
        priority: form.priority,
        contact_phone: form.contactPhone.trim(),
      });

      setAppointments((current) => [data, ...current]);
      setForm(initialForm);
      setLookupKey((k) => k + 1);
      setMessage(`Appointment booked for ${data.patient.full_name}.`);
    } catch (err) {
      setError(err.response?.data?.message || "Unable to book the appointment — please check the form and try again.");
    }
  }

  async function updateStatus(id, status) {
    setBusyId(id); setError("");
    try {
      const { data } = await api.put(`/appointments/${id}/status`, { status });
      setAppointments((current) => current.map((a) => (a.id === id ? data : a)));
      setMessage(status === "missed" ? "Appointment marked as missed." : `Appointment marked as ${status}.`);
    } catch (err) {
      setError(err.response?.data?.message || "Unable to update the appointment — the change was NOT saved, please retry.");
    } finally {
      setBusyId(null);
    }
  }

  async function checkIn(id) {
    setBusyId(id); setError(""); setMessage("");
    try {
      const { data } = await api.post(`/appointments/${id}/check-in`);
      setAppointments((current) => current.map((a) => (a.id === id ? data.appointment : a)));
      setMessage("Patient checked in — sending them straight to triage.");
      navigate(`/encounters/${data.encounter.id}`);
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't check this patient in — please try again.");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="space-y-5 max-w-6xl">
      <PageHeader
        icon={CalendarDays}
        title="Appointments"
        subtitle="Book visits against a real patient record, then check them in on arrival to send them straight to triage."
      />

      <Card className="border-teal-500/20 bg-teal-500/5">
        <div className="grid gap-3 md:grid-cols-5">
          <Stat label="Total" value={stats.total} />
          <Stat label="Scheduled" value={stats.scheduled} />
          <Stat label="Checked in" value={stats.checkedIn} tone="text-teal-600" />
          <Stat label="Missed" value={stats.missed} tone="text-alert" />
          <Stat label="Completed" value={stats.completed} tone="text-moss" />
        </div>
      </Card>

      {error && <p className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">{error}</p>}

      <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
        {canBook ? (
          <Card>
            <div className="mb-4">
              <p className="font-display text-xl">Book an appointment</p>
              <p className="text-sm text-ink/50">Link this booking to a real patient record so it can become an actual visit later.</p>
            </div>

            <form className="space-y-4" onSubmit={handleSubmit}>
              <PatientLookup
                key={lookupKey}
                label="Patient"
                onSelect={({ patientId, patientLabel }) => setForm((f) => ({ ...f, patientId, patientLabel }))}
              />

              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Doctor">
                  <Select value={form.doctorId} onChange={(e) => updateField("doctorId", e.target.value)}>
                    <option value="">Any available doctor</option>
                    {doctors.map((d) => (
                      <option key={d.id} value={d.id}>{d.full_name}{d.department ? ` — ${d.department}` : ""}</option>
                    ))}
                  </Select>
                </Field>
                <Field label="Contact phone">
                  <Input
                    value={form.contactPhone}
                    onChange={(e) => updateField("contactPhone", e.target.value)}
                    placeholder="0999 000 000"
                  />
                </Field>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Department" required>
                  <Select value={form.department} onChange={(e) => updateField("department", e.target.value)}>
                    <option value="General Clinic">General Clinic</option>
                    <option value="Specialist Clinic">Specialist Clinic</option>
                    <option value="Dental">Dental</option>
                    <option value="Maternity">Maternity</option>
                    <option value="Pediatrics">Pediatrics</option>
                  </Select>
                </Field>
                <Field label="Priority">
                  <Select value={form.priority} onChange={(e) => updateField("priority", e.target.value)}>
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                  </Select>
                </Field>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <Field label="Date" required>
                  <Input type="date" value={form.date} onChange={(e) => updateField("date", e.target.value)} />
                </Field>
                <Field label="Time" required>
                  <Input type="time" value={form.time} onChange={(e) => updateField("time", e.target.value)} />
                </Field>
              </div>

              <Field label="Reason for visit">
                <Textarea value={form.reason} onChange={(e) => updateField("reason", e.target.value)} placeholder="Describe the reason for the visit" />
              </Field>

              <Button type="submit" icon={CalendarDays}>Book appointment</Button>
            </form>

            {message && (
              <div className="mt-4 rounded-xl border border-teal-500/20 bg-teal-500/10 px-3 py-2 text-sm text-teal-700">
                {message}
              </div>
            )}
          </Card>
        ) : (
          <Card>
            <p className="font-display text-xl mb-1">Book an appointment</p>
            <p className="text-sm text-ink/50">Reception and nursing staff book appointments. You can view and check in patients from the list.</p>
          </Card>
        )}

        <Card>
          <div className="mb-4">
            <p className="font-display text-xl">Today's follow-up focus</p>
            <p className="text-sm text-ink/50">Past or missed visits should trigger a follow-up call.</p>
          </div>

          <div className="space-y-3">
            <div className="rounded-2xl border border-line bg-surface-alt p-4">
              <div className="flex items-center gap-2 text-alert">
                <AlertTriangle size={16} />
                <p className="font-semibold">Missed visits</p>
              </div>
              <p className="mt-2 text-sm text-ink/60">
                {stats.missed > 0 ? `${stats.missed} appointment${stats.missed > 1 ? "s" : ""} need follow-up.` : "No missed appointments yet."}
              </p>
            </div>
            <div className="rounded-2xl border border-line bg-surface-alt p-4">
              <div className="flex items-center gap-2 text-teal-600">
                <Clock3 size={16} />
                <p className="font-semibold">Upcoming bookings</p>
              </div>
              <p className="mt-2 text-sm text-ink/60">
                {stats.scheduled > 0 ? `${stats.scheduled} appointment${stats.scheduled > 1 ? "s" : ""} still scheduled.` : "No scheduled appointments yet."}
              </p>
            </div>
          </div>
        </Card>
      </div>

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="font-display text-xl">Appointment list</p>
            <p className="text-sm text-ink/50">Review the current queue, check patients in, and update visit outcomes.</p>
          </div>

          <div className="flex flex-wrap gap-2">
            {[
              { value: "all", label: "All" },
              { value: "scheduled", label: "Scheduled" },
              { value: "checked_in", label: "Checked in" },
              { value: "missed", label: "Missed" },
              { value: "completed", label: "Completed" },
            ].map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => setFilter(option.value)}
                className={`rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                  filter === option.value ? "bg-teal-500 text-white" : "bg-surface-alt text-ink/60"
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="mt-6 rounded-2xl border border-dashed border-line bg-surface-alt p-8 text-center text-sm text-ink/50">
            Loading appointments…
          </div>
        ) : visibleAppointments.length === 0 ? (
          <div className="mt-6 rounded-2xl border border-dashed border-line bg-surface-alt p-8 text-center text-sm text-ink/50">
            No appointments in this view yet.
          </div>
        ) : (
          <div className="mt-5 space-y-3">
            {visibleAppointments.map((a) => (
              <div key={a.id} className="rounded-2xl border border-line bg-surface-alt p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <UserRound size={16} className="text-teal-500" />
                      <p className="font-semibold text-ink">{a.patient?.full_name || "Unknown patient"}</p>
                      <span className="text-xs text-ink/40 mrn-mono">{a.patient?.patient_uid}</span>
                    </div>
                    <p className="mt-1 text-sm text-ink/60">{a.reason || "No reason provided yet."}</p>
                  </div>
                  <Badge tone={badgeTone(a.status)}>{badgeLabel(a.status)}</Badge>
                </div>

                <div className="mt-3 grid gap-3 md:grid-cols-3">
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Stethoscope size={15} className="text-teal-500" />
                    <span>{a.department}{a.doctor ? ` · Dr. ${a.doctor.full_name}` : ""}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Clock3 size={15} className="text-teal-500" />
                    <span>{formatDateTime(a.appointment_date, a.appointment_time)}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Phone size={15} className="text-teal-500" />
                    <span>{a.contact_phone || a.patient?.phone || "No phone on record"}</span>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                  {a.status === "scheduled" && canBook && (
                    <>
                      <Button type="button" size="sm" icon={LogIn} disabled={busyId === a.id} onClick={() => checkIn(a.id)}>
                        {busyId === a.id ? "Checking in…" : "Check in → triage"}
                      </Button>
                      <Button type="button" variant="secondary" size="sm" icon={CheckCircle2} disabled={busyId === a.id} onClick={() => updateStatus(a.id, "completed")}>
                        Mark completed
                      </Button>
                      <Button type="button" variant="clay" size="sm" icon={AlertTriangle} disabled={busyId === a.id} onClick={() => updateStatus(a.id, "missed")}>
                        Mark missed
                      </Button>
                    </>
                  )}
                  {a.status === "checked_in" && a.encounter_id && (
                    <Button type="button" variant="secondary" size="sm" icon={LogIn} onClick={() => navigate(`/encounters/${a.encounter_id}`)}>
                      Open visit
                    </Button>
                  )}
                  {a.status === "missed" && canBook && (
                    <Button type="button" variant="secondary" size="sm" icon={CheckCircle2} disabled={busyId === a.id} onClick={() => updateStatus(a.id, "completed")}>
                      Mark completed
                    </Button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}

function Stat({ label, value, tone = "text-ink" }) {
  return (
    <div className="rounded-2xl border border-line bg-surface p-4">
      <p className="text-sm text-ink/50">{label}</p>
      <p className={`mt-1 text-2xl font-semibold ${tone}`}>{value}</p>
    </div>
  );
}