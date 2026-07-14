import { useEffect, useState, useCallback } from "react";
import { Droplets } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function Dialysis() {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/dialysis/sessions").then((res) => setSessions(res.data)).catch(() => setSessions([])).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader icon={Droplets} title="Dialysis & CKD unit" subtitle="Session scheduling, fluid balance tracking, and chronic care follow-up." />

      <NewSessionPanel onSaved={load} />

      {loading ? <LoadingRow /> : sessions.length === 0 ? (
        <EmptyState title="No dialysis sessions recorded yet" />
      ) : (
        <div className="space-y-3">
          {sessions.map((s) => (
            <Card key={s.id}>
              <div className="flex items-center justify-between flex-wrap gap-2">
                <div>
                  <p className="font-semibold">Patient #{s.patient_id} · CKD stage {s.ckd_stage || "—"}</p>
                  <p className="text-xs text-ink/50">{new Date(s.session_date).toLocaleString()}</p>
                </div>
                <Badge tone={s.status === "missed" ? "critical" : s.status === "completed" ? "success" : "muted"}>{s.status}</Badge>
              </div>
              <div className="grid grid-cols-3 gap-2 mt-2 text-sm text-ink/70">
                <span>Pre-weight: {s.pre_weight_kg ?? "—"} kg</span>
                <span>Post-weight: {s.post_weight_kg ?? "—"} kg</span>
                <span>Fluid target: {s.fluid_removal_target_l ?? "—"} L</span>
                <span>Access: {s.vascular_access_type ?? "—"}</span>
              </div>
              {s.complications && <p className="text-xs text-alert mt-1">Complications: {s.complications}</p>}
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

function NewSessionPanel({ onSaved }) {
  const [form, setForm] = useState({ patient_id: "", ckd_stage: "", pre_weight_kg: "", fluid_removal_target_l: "", vascular_access_type: "", complications: "", status: "completed" });
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    if (!form.patient_id) return;
    setSaving(true);
    try {
      await api.post("/dialysis/sessions", {
        ...form, patient_id: Number(form.patient_id),
        pre_weight_kg: form.pre_weight_kg ? Number(form.pre_weight_kg) : undefined,
        fluid_removal_target_l: form.fluid_removal_target_l ? Number(form.fluid_removal_target_l) : undefined,
      });
      setForm({ patient_id: "", ckd_stage: "", pre_weight_kg: "", fluid_removal_target_l: "", vascular_access_type: "", complications: "", status: "completed" });
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">Log a dialysis session</p>
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
        <Field label="Patient ID" required><Input value={form.patient_id} onChange={(e) => setForm({ ...form, patient_id: e.target.value })} /></Field>
        <Field label="CKD stage"><Input value={form.ckd_stage} onChange={(e) => setForm({ ...form, ckd_stage: e.target.value })} placeholder="e.g. Stage 5" /></Field>
        <Field label="Status">
          <Select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
            <option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="missed">Missed</option>
          </Select>
        </Field>
        <Field label="Pre-weight (kg)"><Input type="number" value={form.pre_weight_kg} onChange={(e) => setForm({ ...form, pre_weight_kg: e.target.value })} /></Field>
        <Field label="Fluid removal target (L)"><Input type="number" step="0.1" value={form.fluid_removal_target_l} onChange={(e) => setForm({ ...form, fluid_removal_target_l: e.target.value })} /></Field>
        <Field label="Vascular access"><Input value={form.vascular_access_type} onChange={(e) => setForm({ ...form, vascular_access_type: e.target.value })} placeholder="e.g. AV fistula" /></Field>
        <Field label="Complications" className="md:col-span-3"><Textarea value={form.complications} onChange={(e) => setForm({ ...form, complications: e.target.value })} /></Field>
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Saving…" : "Save session"}</Button>
      </form>
    </Card>
  );
}
