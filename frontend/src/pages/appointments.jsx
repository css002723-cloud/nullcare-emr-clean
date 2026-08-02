import { useEffect, useMemo, useState } from "react";
import {
  AlertTriangle,
  CalendarDays,
  CheckCircle2,
  Clock3,
  Phone,
  Stethoscope,
  UserRound,
} from "lucide-react";
import { Badge, Button, Card, Field, Input, Select, Textarea } from "../components/ui";
import PageHeader from "../components/PageHeader";
import api from "../services/api";

const initialForm = {
  patient_name: "",
  phone: "",
  department: "General Clinic",
  date: "",
  time: "",
  reason: "",
  priority: "routine",
};

function isPastAppointment(date, time) {
  if (!date || !time) return false;
  const parsed = new Date(`${date}T${time}`);
  if (Number.isNaN(parsed.getTime())) return false;
  return parsed < new Date();
}

function formatDateTime(date, time) {
  if (!date || !time) return "Pending";
  const parsed = new Date(`${date}T${time}`);
  if (Number.isNaN(parsed.getTime())) return "Pending";
  return parsed.toLocaleString([], {
    weekday: "short",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function badgeTone(status) {
  if (status === "completed") return "success";
  if (status === "missed") return "critical";
  return "warning";
}

function badgeLabel(status) {
  if (status === "completed") return "Completed";
  if (status === "missed") return "Missed";
  return "Scheduled";
}

export default function Appointments() {
  const [appointments, setAppointments] = useState([]);
  const [form, setForm] = useState(initialForm);
  const [filter, setFilter] = useState("all");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadAppointments() {
      try {
        const { data } = await api.get("/appointments");
        setAppointments(data);
      } catch (error) {
        console.error(error);
        setMessage("Unable to load appointments right now.");
      } finally {
        setLoading(false);
      }
    }

    loadAppointments();
  }, []);

  const stats = useMemo(() => {
    const scheduled = appointments.filter((appointment) => appointment.status === "scheduled").length;
    const missed = appointments.filter((appointment) => appointment.status === "missed").length;
    const completed = appointments.filter((appointment) => appointment.status === "completed").length;

    return { total: appointments.length, scheduled, missed, completed };
  }, [appointments]);

  const visibleAppointments = useMemo(() => {
    if (filter === "all") return appointments;
    return appointments.filter((appointment) => appointment.status === filter);
  }, [appointments, filter]);

  function updateField(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setMessage("");

    if (!form.patient_name.trim() || !form.date || !form.time || !form.department) {
      setMessage("Please complete the patient, date, time, and department fields.");
      return;
    }

    try {
      const { data } = await api.post("/appointments", {
        patient_name: form.patient_name.trim(),
        phone: form.phone.trim(),
        department: form.department,
        appointment_date: form.date,
        appointment_time: form.time,
        reason: form.reason.trim(),
        priority: form.priority,
      });

      setAppointments((current) => [data, ...current]);
      setForm(initialForm);
      setMessage(`Appointment booked for ${data.patient_name}.`);
    } catch (error) {
      console.error(error);
      setMessage(error.response?.data?.message || "Unable to book the appointment.");
    }
  }

  async function updateStatus(id, status) {
    try {
      const { data } = await api.put(`/appointments/${id}/status`, { status });
      setAppointments((current) => current.map((appointment) => (appointment.id === id ? data : appointment)));
      setMessage(status === "missed" ? "Appointment marked as missed." : "Appointment marked as completed.");
    } catch (error) {
      console.error(error);
      setMessage(error.response?.data?.message || "Unable to update the appointment.");
    }
  }

  return (
    <div className="space-y-5 max-w-6xl">
      <PageHeader
        icon={CalendarDays}
        title="Appointments"
        subtitle="Book new visits, follow up on missed visits, and keep the clinic schedule moving."
      />

      <Card className="border-teal-500/20 bg-teal-500/5">
        <div className="grid gap-3 md:grid-cols-4">
          <div className="rounded-2xl border border-line bg-surface p-4">
            <p className="text-sm text-ink/50">Total</p>
            <p className="mt-1 text-2xl font-semibold text-ink">{stats.total}</p>
          </div>
          <div className="rounded-2xl border border-line bg-surface p-4">
            <p className="text-sm text-ink/50">Scheduled</p>
            <p className="mt-1 text-2xl font-semibold text-ink">{stats.scheduled}</p>
          </div>
          <div className="rounded-2xl border border-line bg-surface p-4">
            <p className="text-sm text-ink/50">Missed</p>
            <p className="mt-1 text-2xl font-semibold text-alert">{stats.missed}</p>
          </div>
          <div className="rounded-2xl border border-line bg-surface p-4">
            <p className="text-sm text-ink/50">Completed</p>
            <p className="mt-1 text-2xl font-semibold text-moss">{stats.completed}</p>
          </div>
        </div>
      </Card>

      <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
        <Card>
          <div className="mb-4">
            <p className="font-display text-xl">Book an appointment</p>
            <p className="text-sm text-ink/50">Capture the patient’s visit details so the reception team can follow up.</p>
          </div>

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div className="grid gap-4 md:grid-cols-2">
              <Field label="Patient name" required>
                <Input
                  value={form.patient_name}
                  onChange={(event) => updateField("patient_name", event.target.value)}
                  placeholder="e.g. Mercy Banda"
                />
              </Field>
              <Field label="Phone number">
                <Input
                  value={form.phone}
                  onChange={(event) => updateField("phone", event.target.value)}
                  placeholder="0999 000 000"
                />
              </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <Field label="Department" required>
                <Select
                  value={form.department}
                  onChange={(event) => updateField("department", event.target.value)}
                >
                  <option value="General Clinic">General Clinic</option>
                  <option value="Specialist Clinic">Specialist Clinic</option>
                  <option value="Dental">Dental</option>
                  <option value="Maternity">Maternity</option>
                  <option value="Pediatrics">Pediatrics</option>
                </Select>
              </Field>
              <Field label="Priority">
                <Select value={form.priority} onChange={(event) => updateField("priority", event.target.value)}>
                  <option value="routine">Routine</option>
                  <option value="urgent">Urgent</option>
                  <option value="emergency">Emergency</option>
                </Select>
              </Field>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <Field label="Appointment date" required>
                <Input
                  type="date"
                  value={form.date}
                  onChange={(event) => updateField("date", event.target.value)}
                />
              </Field>
              <Field label="Appointment time" required>
                <Input
                  type="time"
                  value={form.time}
                  onChange={(event) => updateField("time", event.target.value)}
                />
              </Field>
            </div>

            <Field label="Reason for visit">
              <Textarea
                value={form.reason}
                onChange={(event) => updateField("reason", event.target.value)}
                placeholder="Describe the reason for the visit"
              />
            </Field>

            <Button type="submit" icon={CalendarDays}>
              Book appointment
            </Button>
          </form>

          {message && (
            <div className="mt-4 rounded-xl border border-teal-500/20 bg-teal-500/10 px-3 py-2 text-sm text-teal-700">
              {message}
            </div>
          )}
        </Card>

        <Card>
          <div className="mb-4">
            <p className="font-display text-xl">Today’s follow-up focus</p>
            <p className="text-sm text-ink/50">Past or missed visits should trigger a follow-up call.</p>
          </div>

          <div className="space-y-3">
            <div className="rounded-2xl border border-line bg-surface-alt p-4">
              <div className="flex items-center gap-2 text-alert">
                <AlertTriangle size={16} />
                <p className="font-semibold">Missed visits</p>
              </div>
              <p className="mt-2 text-sm text-ink/60">
                {stats.missed > 0
                  ? `${stats.missed} appointment${stats.missed > 1 ? "s" : ""} need follow-up.`
                  : "No missed appointments yet."
                }
              </p>
            </div>
            <div className="rounded-2xl border border-line bg-surface-alt p-4">
              <div className="flex items-center gap-2 text-teal-600">
                <Clock3 size={16} />
                <p className="font-semibold">Upcoming bookings</p>
              </div>
              <p className="mt-2 text-sm text-ink/60">
                {stats.scheduled > 0
                  ? `${stats.scheduled} appointment${stats.scheduled > 1 ? "s" : ""} are still scheduled.`
                  : "No scheduled appointments yet."}
              </p>
            </div>
          </div>
        </Card>
      </div>

      <Card>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="font-display text-xl">Appointment list</p>
            <p className="text-sm text-ink/50">Review the current queue and update visit outcomes.</p>
          </div>

          <div className="flex flex-wrap gap-2">
            {[
              { value: "all", label: "All" },
              { value: "scheduled", label: "Scheduled" },
              { value: "missed", label: "Missed" },
              { value: "completed", label: "Completed" },
            ].map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => setFilter(option.value)}
                className={`rounded-full px-3 py-1.5 text-sm font-medium transition-colors ${
                  filter === option.value
                    ? "bg-teal-500 text-white"
                    : "bg-surface-alt text-ink/60"
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="mt-6 rounded-2xl border border-dashed border-line bg-surface-alt p-8 text-center text-sm text-ink/50">
            Loading appointments...
          </div>
        ) : visibleAppointments.length === 0 ? (
          <div className="mt-6 rounded-2xl border border-dashed border-line bg-surface-alt p-8 text-center text-sm text-ink/50">
            No appointments in this view yet.
          </div>
        ) : (
          <div className="mt-5 space-y-3">
            {visibleAppointments.map((appointment) => (
              <div key={appointment.id} className="rounded-2xl border border-line bg-surface-alt p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <UserRound size={16} className="text-teal-500" />
                      <p className="font-semibold text-ink">{appointment.patient_name}</p>
                    </div>
                    <p className="mt-1 text-sm text-ink/60">{appointment.reason || "No reason provided yet."}</p>
                  </div>
                  <Badge tone={badgeTone(appointment.status)}>{badgeLabel(appointment.status)}</Badge>
                </div>

                <div className="mt-3 grid gap-3 md:grid-cols-3">
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Stethoscope size={15} className="text-teal-500" />
                    <span>{appointment.department}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Clock3 size={15} className="text-teal-500" />
                    <span>{formatDateTime(appointment.date, appointment.time)}</span>
                  </div>
                  <div className="flex items-center gap-2 text-sm text-ink/60">
                    <Phone size={15} className="text-teal-500" />
                    <span>{appointment.phone || "No phone provided"}</span>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                  {appointment.status === "scheduled" && (
                    <>
                      <Button type="button" variant="secondary" size="sm" icon={CheckCircle2} onClick={() => updateStatus(appointment.id, "completed")}>
                        Mark completed
                      </Button>
                      <Button type="button" variant="clay" size="sm" icon={AlertTriangle} onClick={() => updateStatus(appointment.id, "missed")}>
                        Mark missed
                      </Button>
                    </>
                  )}
                  {appointment.status === "missed" && (
                    <Button type="button" variant="secondary" size="sm" icon={CheckCircle2} onClick={() => updateStatus(appointment.id, "completed")}>
                      Mark completed
                    </Button>
                  )}
                  {appointment.status === "completed" && (
                    <Button type="button" variant="secondary" size="sm" icon={CalendarDays} onClick={() => updateStatus(appointment.id, "scheduled")}>
                      Re-open
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
