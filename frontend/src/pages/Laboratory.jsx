import { useEffect, useState, useCallback } from "react";
import { FlaskConical } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { useAuth } from "../context/AuthContext";

const STATUS_TABS = ["ordered", "collected", "received", "resulted"];

export default function Laboratory() {
  const { hasRole } = useAuth();
  const [orders, setOrders] = useState([]);
  const [tab, setTab] = useState("ordered");
  const [loading, setLoading] = useState(true);
  const [catalog, setCatalog] = useState({});

  const load = useCallback((status) => {
    setLoading(true);
    api.get("/lab/orders", { params: status ? { status } : {} })
      .then((res) => setOrders(res.data))
      .catch(() => setOrders([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(tab); }, [tab, load]);
  useEffect(() => { api.get("/lab/catalog").then((res) => setCatalog(res.data)).catch(() => {}); }, []);

  async function collect(id) {
    await api.post(`/lab/orders/${id}/collect`);
    load(tab);
  }
  async function receive(id) {
    await api.post(`/lab/orders/${id}/receive`);
    load(tab);
  }

  return (
    <div className="space-y-5">
      <PageHeader
        icon={FlaskConical}
        title="Laboratory"
        subtitle="Specimen tracking with LOINC-coded test results, from order receipt through verification."
      />

      {hasRole("doctor", "nurse") && <QuickOrderPanel catalog={catalog} onOrdered={() => load(tab)} />}

      <div className="flex gap-2 flex-wrap">
        {STATUS_TABS.map((s) => (
          <button
            key={s}
            onClick={() => setTab(s)}
            className={`px-3 py-1.5 rounded-full text-sm font-medium capitalize ${
              tab === s ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"
            }`}
          >
            {s}
          </button>
        ))}
      </div>

      {loading ? <LoadingRow /> : orders.length === 0 ? (
        <EmptyState title={`No ${tab} orders`} />
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <LabOrderRow key={o.id} order={o} onCollect={() => collect(o.id)} onReceive={() => receive(o.id)} onResulted={() => load(tab)} />
          ))}
        </div>
      )}
    </div>
  );
}

function QuickOrderPanel({ catalog, onOrdered }) {
  const [encounterId, setEncounterId] = useState("");
  const [testCode, setTestCode] = useState("");
  const [specimenType, setSpecimenType] = useState("");
  const [priority, setPriority] = useState("routine");
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    if (!encounterId || !testCode) return;
    setSaving(true);
    try {
      await api.post("/lab/orders", {
        encounter_id: Number(encounterId), test_code: testCode,
        specimen_type: specimenType, priority,
      });
      setEncounterId(""); setTestCode(""); setSpecimenType("");
      onOrdered();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">Order a test (LOINC-coded)</p>
      <form onSubmit={submit} className="grid md:grid-cols-4 gap-3 items-end">
        <Field label="Encounter ID" hint="Find on the patient's encounter page">
          <Input value={encounterId} onChange={(e) => setEncounterId(e.target.value)} placeholder="e.g. 12" />
        </Field>
        <Field label="Test">
          <Select value={testCode} onChange={(e) => setTestCode(e.target.value)}>
            <option value="">Select…</option>
            {Object.entries(catalog).map(([code, meta]) => (
              <option key={code} value={code}>{meta.loinc_display} ({meta.loinc_code})</option>
            ))}
          </Select>
        </Field>
        <Field label="Specimen">
          <Input value={specimenType} onChange={(e) => setSpecimenType(e.target.value)} placeholder="e.g. venous blood" />
        </Field>
        <Field label="Priority">
          <Select value={priority} onChange={(e) => setPriority(e.target.value)}>
            <option value="routine">Routine</option>
            <option value="urgent">Urgent</option>
            <option value="stat">Stat</option>
          </Select>
        </Field>
        <Button type="submit" disabled={saving} className="md:col-span-4">{saving ? "Ordering…" : "Place lab order"}</Button>
      </form>
    </Card>
  );
}

function LabOrderRow({ order, onCollect, onReceive, onResulted }) {
  const [showResultForm, setShowResultForm] = useState(false);
  const [result, setResult] = useState({ result_value: "", unit: "", reference_range: "", is_critical: false, is_abnormal: false, interpretation: "" });
  const [saving, setSaving] = useState(false);

  async function submitResult(e) {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/lab/orders/${order.id}/result`, result);
      setShowResultForm(false);
      onResulted();
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <p className="font-semibold">{order.loinc_display || order.test_code}</p>
          <p className="text-xs text-ink/50 mrn-mono">LOINC {order.loinc_code || "n/a"} · barcode {order.barcode}</p>
        </div>
        <div className="flex items-center gap-2">
          <Badge tone="muted">{order.status}</Badge>
          {order.status === "ordered" && <Button size="sm" onClick={onCollect}>Mark specimen collected</Button>}
          {order.status === "collected" && <Button size="sm" onClick={onReceive}>Mark received in lab</Button>}
          {order.status === "received" && !showResultForm && (
            <Button size="sm" onClick={() => setShowResultForm(true)}>Enter result</Button>
          )}
        </div>
      </div>

      {order.result && (
        <div className="mt-2 text-sm bg-surface-alt rounded-lg p-2">
          Result: <span className="font-semibold">{order.result.result_value} {order.result.unit}</span>
          {order.result.reference_range && <span className="text-ink/50"> (ref: {order.result.reference_range})</span>}
          {order.result.is_critical && <Badge tone="critical" className="ml-2">Critical</Badge>}
        </div>
      )}

      {showResultForm && (
        <form onSubmit={submitResult} className="mt-3 grid md:grid-cols-2 gap-3 border-t border-line pt-3">
          <Field label="Result value" required><Input value={result.result_value} onChange={(e) => setResult({ ...result, result_value: e.target.value })} /></Field>
          <Field label="Unit"><Input value={result.unit} onChange={(e) => setResult({ ...result, unit: e.target.value })} /></Field>
          <Field label="Reference range"><Input value={result.reference_range} onChange={(e) => setResult({ ...result, reference_range: e.target.value })} /></Field>
          <Field label="Interpretation"><Input value={result.interpretation} onChange={(e) => setResult({ ...result, interpretation: e.target.value })} /></Field>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={result.is_abnormal} onChange={(e) => setResult({ ...result, is_abnormal: e.target.checked })} /> Abnormal
          </label>
          <label className="flex items-center gap-2 text-sm text-alert">
            <input type="checkbox" checked={result.is_critical} onChange={(e) => setResult({ ...result, is_critical: e.target.checked })} /> Critical value — triggers alert
          </label>
          <Button type="submit" className="md:col-span-2" disabled={saving}>{saving ? "Saving…" : "Save result"}</Button>
        </form>
      )}
    </Card>
  );
}
