import { useEffect, useState, useCallback } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { Skull } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow } from "../components/ui";
import PatientRibbon from "../components/PatientRibbon";
import { useAuth } from "../context/AuthContext";

const NOTE_TYPES = [
  { value: "daily_review", label: "Daily ICU review" },
  { value: "procedure", label: "Critical care procedure note" },
];

export default function ICUPatient() {
  const { encounterId } = useParams();
  const navigate = useNavigate();
  const { hasRole } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/icu/patient/${encounterId}`);
      setData(res.data);
    } catch { /* offline */ } finally {
      setLoading(false);
    }
  }, [encounterId]);

  useEffect(() => { load(); }, [load]);

  function flash(text) { setMsg(text); setTimeout(() => setMsg(""), 3000); }

  if (loading) return <LoadingRow label="Loading critical care chart…" />;
  if (!data) return <p className="text-sm text-ink/50">Chart unavailable — you may be offline.</p>;

  const admissionNote = data.notes.find((n) => n.note_type === "admission");
  const reviewNotes = data.notes.filter((n) => n.note_type !== "admission" && n.note_type !== "discharge_summary");
  const dischargeNote = data.notes.find((n) => n.note_type === "discharge_summary");

  const vitalsChartData = data.vitals.map((v) => ({
    time: new Date(v.created_at).toLocaleString(undefined, { hour: "2-digit", minute: "2-digit", day: "2-digit", month: "short" }),
    spo2: v.spo2, pulse: v.pulse_rate, temp: v.temperature_c,
  }));

  return (
    <div className="space-y-5 -mt-6 md:-mt-8">
      <PatientRibbon patient={data.patient} encounter={data} referral={data.referral} />
      {msg && <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2">{msg}</p>}

      {admissionNote && (
        <Card className="border-teal-200 dark:border-teal-500/40">
          <p className="font-display text-lg mb-2">Admission note</p>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-ink/60 mb-2">
            <span>Ventilation: <b className="text-ink">{admissionNote.ventilation_status || "—"}</b></span>
            <span>O2 therapy: <b className="text-ink">{admissionNote.oxygen_therapy || "—"}</b></span>
            <span>Sedation: <b className="text-ink">{admissionNote.sedation_assessment || "—"}</b></span>
            <span>Inotropes: <b className="text-ink">{admissionNote.inotropes || "—"}</b></span>
          </div>
          {admissionNote.sepsis_alert && <Badge tone="critical" icon={Skull} className="mb-2">Sepsis alert on admission</Badge>}
          <p className="text-sm">{admissionNote.body}</p>
        </Card>
      )}

      <Card>
        <p className="font-display text-lg mb-3">Continuous observation chart</p>
        {vitalsChartData.length === 0 ? (
          <p className="text-sm text-ink/40">No observations recorded yet.</p>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <LineChart data={vitalsChartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-line))" />
              <XAxis dataKey="time" tick={{ fontSize: 10, fill: "rgb(var(--color-ink) / 0.6)" }} />
              <YAxis tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
              <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
              <Line type="monotone" dataKey="spo2" name="SpO2 %" stroke="#3F8C84" dot={{ r: 3 }} />
              <Line type="monotone" dataKey="pulse" name="Pulse" stroke="#0F4C4A" dot={{ r: 3 }} />
              <Line type="monotone" dataKey="temp" name="Temp °C" stroke="#C8443C" dot={{ r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        )}
      </Card>

      {hasRole("doctor", "nurse") && <ReviewNotePanel encounterId={encounterId} onSaved={() => { load(); flash("Note saved"); }} />}

      <Card>
        <p className="font-display text-lg mb-3">Daily reviews & procedure notes</p>
        {reviewNotes.length === 0 ? (
          <p className="text-sm text-ink/40">No entries yet.</p>
        ) : (
          <div className="space-y-2 max-h-96 overflow-y-auto">
            {reviewNotes.map((n) => (
              <div key={n.id} className="text-sm border border-line rounded-lg p-2.5">
                <div className="flex items-center justify-between mb-1">
                  <p className="font-semibold capitalize">{n.note_type.replace("_", " ")}</p>
                  <span className="text-xs text-ink/40">{new Date(n.created_at).toLocaleString()}</span>
                </div>
                <div className="grid grid-cols-2 gap-1 text-xs text-ink/60 mb-1">
                  {n.ventilation_status && <span>Vent: {n.ventilation_status}</span>}
                  {n.oxygen_therapy && <span>O2: {n.oxygen_therapy}</span>}
                  {n.sedation_assessment && <span>Sedation: {n.sedation_assessment}</span>}
                  {n.inotropes && <span>Inotropes: {n.inotropes}</span>}
                  {n.fluid_balance_summary && <span>Fluid balance: {n.fluid_balance_summary}</span>}
                </div>
                {n.sepsis_alert && <Badge tone="critical" icon={Skull} className="mb-1">Sepsis alert</Badge>}
                <p>{n.body}</p>
              </div>
            ))}
          </div>
        )}
      </Card>

      {dischargeNote && (
        <Card className="border-moss/30 bg-moss/5">
          <p className="font-display text-lg mb-2">ICU discharge summary</p>
          <p className="text-sm">{dischargeNote.body}</p>
        </Card>
      )}

      {hasRole("doctor") && !dischargeNote && (
        <DischargePanel encounterId={encounterId} onDone={() => navigate("/icu")} />
      )}
    </div>
  );
}

function ReviewNotePanel({ encounterId, onSaved }) {
  const [form, setForm] = useState({
    note_type: "daily_review", ventilation_status: "", oxygen_therapy: "", sedation_assessment: "",
    inotropes: "", fluid_balance_summary: "", sepsis_alert: false, body: "",
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    if (!form.body) return;
    setSaving(true);
    setError("");
    try {
      await api.post("/icu/notes", { encounter_id: Number(encounterId), ...form });
      setForm({ note_type: "daily_review", ventilation_status: "", oxygen_therapy: "", sedation_assessment: "", inotropes: "", fluid_balance_summary: "", sepsis_alert: false, body: "" });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save the note — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Add daily review / procedure note</p>
      <form onSubmit={submit} className="grid md:grid-cols-2 gap-3">
        <Select value={form.note_type} onChange={(e) => setForm({ ...form, note_type: e.target.value })}>
          {NOTE_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
        </Select>
        <Field label="Ventilation status">
          <Select value={form.ventilation_status} onChange={(e) => setForm({ ...form, ventilation_status: e.target.value })}>
            <option value="">Unchanged</option>
            <option value="none">None</option>
            <option value="non_invasive">Non-invasive</option>
            <option value="invasive">Invasive</option>
          </Select>
        </Field>
        <Field label="Oxygen therapy"><Input value={form.oxygen_therapy} onChange={(e) => setForm({ ...form, oxygen_therapy: e.target.value })} /></Field>
        <Field label="Sedation assessment"><Input value={form.sedation_assessment} onChange={(e) => setForm({ ...form, sedation_assessment: e.target.value })} /></Field>
        <Field label="Inotropes / vasopressors"><Input value={form.inotropes} onChange={(e) => setForm({ ...form, inotropes: e.target.value })} /></Field>
        <Field label="Fluid balance summary" className="md:col-span-2"><Input value={form.fluid_balance_summary} onChange={(e) => setForm({ ...form, fluid_balance_summary: e.target.value })} placeholder="e.g. +450ml over 24h" /></Field>
        <Field label="Findings / plan" className="md:col-span-2"><Textarea value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} /></Field>
        <label className="flex items-center gap-2 text-sm text-alert md:col-span-2">
          <input type="checkbox" checked={form.sepsis_alert} onChange={(e) => setForm({ ...form, sepsis_alert: e.target.checked })} />
          Raise sepsis alert
        </label>
        <Button type="submit" disabled={saving} className="md:col-span-2">{saving ? "Saving…" : "Add note"}</Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1 md:col-span-2">{error}</p>}
      </form>
    </Card>
  );
}

function DischargePanel({ encounterId, onDone }) {
  const [outcome, setOutcome] = useState("discharged");
  const [summary, setSummary] = useState("");
  const [confirmDeath, setConfirmDeath] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    if (outcome === "died" && !confirmDeath) return;
    setSaving(true);
    setError("");
    try {
      await api.post("/icu/discharge", { encounter_id: Number(encounterId), outcome, summary });
      onDone();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't complete the ICU discharge — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">ICU discharge</p>
      <form onSubmit={submit} className="space-y-3 w-full">
        <Select value={outcome} onChange={(e) => { setOutcome(e.target.value); setConfirmDeath(false); }}>
          <option value="discharged">Discharge / step down</option>
          <option value="admitted">Transfer to general ward</option>
          <option value="referred_out">Refer to another facility</option>
          <option value="died">Death documentation</option>
        </Select>
        <Textarea placeholder="Discharge summary" value={summary} onChange={(e) => setSummary(e.target.value)} />
        {outcome === "died" && (
          <label className="flex items-center gap-2 text-sm text-alert">
            <input type="checkbox" checked={confirmDeath} onChange={(e) => setConfirmDeath(e.target.checked)} />
            I confirm this action documents a patient death and cannot be casually undone.
          </label>
        )}
        <Button type="submit" variant={outcome === "died" ? "danger" : "primary"} disabled={saving || (outcome === "died" && !confirmDeath)}>
          {saving ? "Saving…" : "Confirm ICU discharge"}
        </Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}
