import { useEffect, useState, useCallback } from "react";
import { ScanLine } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { useAuth } from "../context/AuthContext";

const MODALITIES = { CR: "X-ray (CR)", CT: "CT scan", US: "Ultrasound", MR: "MRI", DX: "Digital X-ray" };

export default function Imaging() {
  const { hasRole } = useAuth();
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/imaging/orders").then((res) => setOrders(res.data)).catch(() => setOrders([])).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader
        icon={ScanLine}
        title="Medical imaging"
        subtitle="DICOM-aware requests — each study carries a modality, accession number, and a placeholder study instance UID ready for future PACS integration."
      />

      {hasRole("doctor") && <OrderPanel onOrdered={load} />}

      {loading ? <LoadingRow /> : orders.length === 0 ? (
        <EmptyState title="No imaging orders yet" />
      ) : (
        <div className="space-y-3">
          {orders.map((o) => <ImagingRow key={o.id} order={o} canReport={hasRole("radiologist")} canReview={hasRole("doctor")} onSaved={load} />)}
        </div>
      )}
    </div>
  );
}

function OrderPanel({ onOrdered }) {
  const [form, setForm] = useState({ encounter_id: "", modality: "", study_description: "", body_site: "", clinical_indication: "", is_pregnancy_checked: false, priority: "routine" });
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    if (!form.encounter_id || !form.modality) return;
    setSaving(true);
    try {
      await api.post("/imaging/orders", { ...form, encounter_id: Number(form.encounter_id) });
      setForm({ encounter_id: "", modality: "", study_description: "", body_site: "", clinical_indication: "", is_pregnancy_checked: false, priority: "routine" });
      onOrdered();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">Request imaging study</p>
      <form onSubmit={submit} className="grid md:grid-cols-2 gap-3">
        <Field label="Encounter ID"><Input value={form.encounter_id} onChange={(e) => setForm({ ...form, encounter_id: e.target.value })} /></Field>
        <Field label="Modality">
          <Select value={form.modality} onChange={(e) => setForm({ ...form, modality: e.target.value })}>
            <option value="">Select…</option>
            {Object.entries(MODALITIES).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </Select>
        </Field>
        <Field label="Study description"><Input value={form.study_description} onChange={(e) => setForm({ ...form, study_description: e.target.value })} placeholder="e.g. Chest PA view" /></Field>
        <Field label="Body site"><Input value={form.body_site} onChange={(e) => setForm({ ...form, body_site: e.target.value })} /></Field>
        <Field label="Clinical indication" className="md:col-span-2"><Textarea value={form.clinical_indication} onChange={(e) => setForm({ ...form, clinical_indication: e.target.value })} /></Field>
        <label className="flex items-center gap-2 text-sm md:col-span-2">
          <input type="checkbox" checked={form.is_pregnancy_checked} onChange={(e) => setForm({ ...form, is_pregnancy_checked: e.target.checked })} />
          Pregnancy status confirmed (required safety check before ionizing radiation studies)
        </label>
        <Button type="submit" disabled={saving} className="md:col-span-2">{saving ? "Requesting…" : "Request study"}</Button>
      </form>
    </Card>
  );
}

function ImagingRow({ order, canReport, canReview, onSaved }) {
  const [showReport, setShowReport] = useState(false);
  const [report, setReport] = useState({ findings: "", impression: "", is_critical_finding: false });
  const [saving, setSaving] = useState(false);

  async function submitReport(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/imaging/orders/${order.id}/report`, report);
      setShowReport(false);
      onSaved();
    } finally {
      setSaving(false);
    }
  }

  async function reviewReport() {
    await api.post(`/imaging/reports/${order.report.id}/review`);
    onSaved();
  }

  return (
    <Card>
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <p className="font-semibold">{MODALITIES[order.modality] || order.modality} — {order.study_description}</p>
          <p className="text-xs text-ink/50 mrn-mono">Accession {order.accession_number}</p>
          {order.safety_checklist_notes?.includes("SYSTEM FLAG") && (
            <Badge tone="warning" className="mt-1">Pregnancy status not confirmed</Badge>
          )}
        </div>
        <div className="flex items-center gap-2">
          <Badge tone="muted">{order.status}</Badge>
          {canReport && order.status !== "reported" && !showReport && (
            <Button size="sm" onClick={() => setShowReport(true)}>Write report</Button>
          )}
          {canReview && order.report && !order.report.reviewed_by_clinician && (
            <Button size="sm" onClick={reviewReport}>Mark reviewed</Button>
          )}
        </div>
      </div>

      {order.report && (
        <div className="mt-2 text-sm bg-surface-alt rounded-lg p-2">
          <p><span className="font-semibold">Findings:</span> {order.report.findings}</p>
          <p><span className="font-semibold">Impression:</span> {order.report.impression}</p>
          {order.report.is_critical_finding && <Badge tone="critical" className="mt-1">Critical finding</Badge>}
        </div>
      )}

      {showReport && (
        <form onSubmit={submitReport} className="mt-3 space-y-2 border-t border-line pt-3">
          <Field label="Findings"><Textarea value={report.findings} onChange={(e) => setReport({ ...report, findings: e.target.value })} /></Field>
          <Field label="Impression"><Textarea value={report.impression} onChange={(e) => setReport({ ...report, impression: e.target.value })} /></Field>
          <label className="flex items-center gap-2 text-sm text-alert">
            <input type="checkbox" checked={report.is_critical_finding} onChange={(e) => setReport({ ...report, is_critical_finding: e.target.checked })} /> Critical finding — alert ordering clinician
          </label>
          <Button type="submit" disabled={saving}>{saving ? "Saving…" : "Submit report"}</Button>
        </form>
      )}
    </Card>
  );
}
