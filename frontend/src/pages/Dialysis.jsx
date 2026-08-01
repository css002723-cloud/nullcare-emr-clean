import { useEffect, useState, useCallback } from "react";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { Droplets, ArrowLeft, CalendarClock } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";

export default function Dialysis() {
  const [selectedPatient, setSelectedPatient] = useState(null); // { patientId, patientLabel }
  const [roster, setRoster] = useState([]);
  const [loadingRoster, setLoadingRoster] = useState(true);
  const [lookupKey, setLookupKey] = useState(0);

  const loadRoster = useCallback(() => {
    setLoadingRoster(true);
    api.get("/dialysis/patients").then((res) => setRoster(res.data)).catch(() => setRoster([])).finally(() => setLoadingRoster(false));
  }, []);

  useEffect(() => { loadRoster(); }, [loadRoster]);

  if (selectedPatient) {
    return (
      <DialysisPatientView
        patientId={selectedPatient.patientId}
        patientLabel={selectedPatient.patientLabel}
        onBack={() => { setSelectedPatient(null); setLookupKey((k) => k + 1); loadRoster(); }}
      />
    );
  }

  return (
    <div className="space-y-5">
      <PageHeader icon={Droplets} title="Dialysis & CKD unit" subtitle="Pick a patient to view their full dialysis history and log today's session." />

      <Card>
        <PatientLookup
          key={lookupKey}
          label="Find a patient by name or MRN"
          onSelect={(sel) => sel.patientId && setSelectedPatient(sel)}
        />
      </Card>

      <div>
        <p className="font-display text-lg mb-3">Patients with dialysis history</p>
        {loadingRoster ? <LoadingRow /> : roster.length === 0 ? (
          <EmptyState icon={Droplets} title="No dialysis patients tracked yet" hint="Search for a patient above to log their first session." />
        ) : (
          <div className="grid md:grid-cols-2 gap-3">
            {roster.map((p) => (
              <Card
                key={p.patient_id}
                className="cursor-pointer hover:border-teal-300 transition-colors"
                onClick={() => setSelectedPatient({ patientId: p.patient_id, patientLabel: `${p.full_name} (${p.patient_uid})` })}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-semibold">{p.full_name}</p>
                    <p className="text-xs text-ink/50 mrn-mono">{p.patient_uid} · CKD {p.ckd_stage || "stage unrecorded"}</p>
                  </div>
                  <div className="text-right">
                    <Badge tone={p.missed_sessions > 0 ? "warning" : "neutral"}>{p.total_sessions} sessions</Badge>
                    {p.missed_sessions > 0 && <p className="text-xs text-alert mt-1">{p.missed_sessions} missed</p>}
                  </div>
                </div>
                {p.latest_session_date && (
                  <p className="text-xs text-ink/40 mt-2 flex items-center gap-1">
                    <CalendarClock size={12} /> Last session {new Date(p.latest_session_date).toLocaleDateString()}
                  </p>
                )}
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function DialysisPatientView({ patientId, patientLabel, onBack }) {
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get(`/dialysis/dashboard/${patientId}`).then((res) => setDashboard(res.data)).catch(() => setDashboard(null)).finally(() => setLoading(false));
  }, [patientId]);

  useEffect(() => { load(); }, [load]);

  const weightChartData = dashboard?.sessions
    .filter((s) => s.status === "completed")
    .map((s) => ({
      date: new Date(s.session_date).toLocaleDateString(undefined, { month: "short", day: "numeric" }),
      pre: s.pre_weight_kg, post: s.post_weight_kg,
    })) || [];

  return (
    <div className="space-y-5">
      <button onClick={onBack} className="flex items-center gap-1.5 text-sm font-medium text-teal-600 dark:text-teal-300 hover:underline">
        <ArrowLeft size={15} /> Back to patient list
      </button>

      <PageHeader icon={Droplets} title={patientLabel} subtitle="Dialysis and chronic kidney disease history" />

      {loading ? <LoadingRow /> : !dashboard ? (
        <p className="text-sm text-ink/50">Couldn't load this patient's history — you may be offline.</p>
      ) : (
        <>
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <Card className="p-4">
              <p className="text-xs text-ink/50">Total sessions</p>
              <p className="font-display text-2xl">{dashboard.total_sessions}</p>
            </Card>
            <Card className="p-4">
              <p className="text-xs text-ink/50">Missed sessions</p>
              <p className="font-display text-2xl">{dashboard.missed_sessions}</p>
              {dashboard.missed_sessions > 0 && <Badge tone="warning" className="mt-1">Follow up needed</Badge>}
            </Card>
            <Card className="p-4 col-span-2 md:col-span-1">
              <p className="text-xs text-ink/50">Adherence</p>
              <p className="font-display text-2xl">
                {dashboard.total_sessions > 0
                  ? `${Math.round(((dashboard.total_sessions - dashboard.missed_sessions) / dashboard.total_sessions) * 100)}%`
                  : "—"}
              </p>
            </Card>
          </div>

          {weightChartData.length > 0 && (
            <Card>
              <p className="font-display text-lg mb-3">Pre/post-dialysis weight trend</p>
              <ResponsiveContainer width="100%" height={220}>
                <LineChart data={weightChartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-line))" />
                  <XAxis dataKey="date" tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
                  <YAxis tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} domain={["auto", "auto"]} />
                  <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
                  <Line type="monotone" dataKey="pre" name="Pre-weight (kg)" stroke="#D98E2F" dot={{ r: 3 }} />
                  <Line type="monotone" dataKey="post" name="Post-weight (kg)" stroke="#0F4C4A" dot={{ r: 3 }} />
                </LineChart>
              </ResponsiveContainer>
            </Card>
          )}

          <LogSessionPanel patientId={patientId} onSaved={load} />

          <Card>
            <p className="font-display text-lg mb-3">Session history</p>
            {dashboard.sessions.length === 0 ? (
              <p className="text-sm text-ink/40">No sessions logged yet.</p>
            ) : (
              <div className="space-y-2 max-h-96 overflow-y-auto">
                {[...dashboard.sessions].reverse().map((s) => (
                  <div key={s.id} className="flex items-center justify-between text-sm border border-line rounded-lg p-2.5">
                    <div>
                      <p className="font-medium">{new Date(s.session_date).toLocaleString()}</p>
                      <p className="text-xs text-ink/50">
                        {s.pre_weight_kg ?? "—"}kg → {s.post_weight_kg ?? "—"}kg
                        {s.vascular_access_type && ` · ${s.vascular_access_type}`}
                      </p>
                      {s.complications && <p className="text-xs text-alert mt-0.5">{s.complications}</p>}
                    </div>
                    <Badge tone={s.status === "missed" ? "critical" : s.status === "completed" ? "success" : "muted"}>{s.status}</Badge>
                  </div>
                ))}
              </div>
            )}
          </Card>
        </>
      )}
    </div>
  );
}

function LogSessionPanel({ patientId, onSaved }) {
  const [form, setForm] = useState({ ckd_stage: "", pre_weight_kg: "", post_weight_kg: "", fluid_removal_target_l: "", vascular_access_type: "", complications: "", status: "completed", session_date: "" });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setError("");
    setSaving(true);
    try {
      await api.post("/dialysis/sessions", {
        patient_id: patientId, ...form,
        pre_weight_kg: form.pre_weight_kg ? Number(form.pre_weight_kg) : undefined,
        post_weight_kg: form.post_weight_kg ? Number(form.post_weight_kg) : undefined,
        fluid_removal_target_l: form.fluid_removal_target_l ? Number(form.fluid_removal_target_l) : undefined,
      });
      setForm({ ckd_stage: "", pre_weight_kg: "", post_weight_kg: "", fluid_removal_target_l: "", vascular_access_type: "", complications: "", status: "completed", session_date: "" });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save the session — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">Log today's session / schedule next</p>
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
        <Field label="Status">
          <Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            <option value="completed">Completed today</option>
            <option value="scheduled">Schedule next session</option>
            <option value="missed">Mark missed session</option>
          </Select>
        </Field>
        <Field label={form.status === "scheduled" ? "Scheduled date" : "Session date/time"}>
          <Input type="datetime-local" value={form.session_date} onChange={(e) => setForm({ ...form, session_date: e.target.value })} />
        </Field>
        <Field label="CKD stage"><Input value={form.ckd_stage} onChange={(e) => setForm({ ...form, ckd_stage: e.target.value })} placeholder="e.g. Stage 5" /></Field>
        <Field label="Pre-weight (kg)"><Input type="number" value={form.pre_weight_kg} onChange={(e) => setForm({ ...form, pre_weight_kg: e.target.value })} /></Field>
        <Field label="Post-weight (kg)"><Input type="number" value={form.post_weight_kg} onChange={(e) => setForm({ ...form, post_weight_kg: e.target.value })} /></Field>
        <Field label="Fluid removal target (L)"><Input type="number" step="0.1" value={form.fluid_removal_target_l} onChange={(e) => setForm({ ...form, fluid_removal_target_l: e.target.value })} /></Field>
        <Field label="Vascular access"><Input value={form.vascular_access_type} onChange={(e) => setForm({ ...form, vascular_access_type: e.target.value })} placeholder="e.g. AV fistula" /></Field>
        <Field label="Complications" className="md:col-span-2"><Textarea value={form.complications} onChange={(e) => setForm({ ...form, complications: e.target.value })} /></Field>
        {error && <p className="text-sm text-alert md:col-span-3">{error}</p>}
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Saving…" : "Save"}</Button>
      </form>
    </Card>
  );
}
