import { useEffect, useState, useCallback } from "react";
import { useParams, useNavigate, Link } from "react-router-dom";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, priorityTone } from "../components/ui";
import PatientRibbon from "../components/PatientRibbon";
import BillingPanel from "../components/BillingPanel";
import ClinicalNoteForm from "../components/ClinicalNoteForm";
import { useAuth } from "../context/AuthContext";
import { createWithOfflineFallback } from "../offline/offlineResource";

const DEPARTMENTS = ["laboratory", "imaging", "pharmacy", "dialysis", "ward", "billing", "consultation"];

export default function EncounterWorkspace() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasRole, user } = useAuth();
  const [encounter, setEncounter] = useState(null);
  const [patient, setPatient] = useState(null);
  const [vitals, setVitals] = useState([]);
  const [notes, setNotes] = useState([]);
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const encRes = await api.get(`/encounters/${id}`);
      setEncounter(encRes.data);
      setPatient(encRes.data.patient);
      const [vRes, nRes, oRes] = await Promise.all([
        api.get(`/vitals/encounter/${id}`),
        api.get(`/encounters/${id}/notes`),
        api.get(`/orders`, { params: { encounter_id: id } }),
      ]);
      setVitals(vRes.data);
      setNotes(nRes.data);
      setOrders(oRes.data);
    } catch {
      // offline — page will show what little cached state exists in memory
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { load(); }, [load]);

  function flash(text) {
    setMsg(text);
    setTimeout(() => setMsg(""), 3000);
  }

  if (loading) return <LoadingRow label="Loading encounter…" />;
  if (!encounter) return <p className="text-sm text-ink/50">Encounter unavailable — you may be offline.</p>;

  return (
    <div className="space-y-5 -mt-6 md:-mt-8">
      <PatientRibbon
        patient={patient}
        encounter={encounter}
        extra={
          <>
            <Badge tone={priorityTone(encounter.priority)}>{encounter.priority}</Badge>
            <Badge tone="muted">{encounter.stage.replace("_", " ")}</Badge>
          </>
        }
      />
      {msg && <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2">{msg}</p>}

      {hasRole("nurse", "doctor") && (
        <VitalsPanel encounterId={id} vitals={vitals} onSaved={() => { load(); flash("Vitals recorded"); }} />
      )}

      {hasRole("billing", "admin") && (
        <BillingPanel encounterId={id} patientId={patient?.id} onSaved={() => { load(); flash("Invoice created"); }} />
      )}

      <ClinicalNoteForm encounterId={id} notes={notes} canWrite={hasRole("doctor", "nurse")} onSaved={() => { load(); flash("Note saved"); }} />

      <div className="grid md:grid-cols-2 gap-5">
        {hasRole("doctor") && <OrdersPanel encounterId={id} orders={orders} onSaved={() => { load(); flash("Order placed"); }} />}
        {hasRole("doctor", "nurse") && <ReferralPanel encounterId={id} currentDept={encounter.current_department} onSaved={() => { load(); flash("Patient referred"); }} />}
      </div>

      {hasRole("doctor") && <PrescribePanel encounterId={id} onSaved={() => flash("Prescription sent to pharmacy")} />}

      {hasRole("doctor", "nurse") && (
        <DispositionPanel
          encounterId={id}
          patientSex={patient?.sex}
          onSaved={() => { load(); flash("Disposition recorded"); }}
          onClosed={() => navigate("/dashboard")}
        />
      )}
    </div>
  );
}

function VitalsPanel({ encounterId, vitals, onSaved }) {
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const latest = vitals[vitals.length - 1];

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      const payload = { encounter_id: Number(encounterId) };
      Object.entries(form).forEach(([k, v]) => { if (v !== "") payload[k] = Number(v); });
      await createWithOfflineFallback("vitals", "/vitals", payload);
      setForm({});
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save vitals — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Vital signs</p>
      {latest && (
        <div className="text-xs text-ink/60 mb-3 grid grid-cols-3 gap-2">
          <span>Temp: {latest.temperature_c ?? "—"}°C</span>
          <span>BP: {latest.blood_pressure_systolic ?? "—"}/{latest.blood_pressure_diastolic ?? "—"}</span>
          <span>Pulse: {latest.pulse_rate ?? "—"}</span>
          <span>RR: {latest.respiratory_rate ?? "—"}</span>
          <span>SpO2: {latest.spo2 ?? "—"}%</span>
          {latest.is_abnormal && <Badge tone="critical">EWS {latest.early_warning_score}</Badge>}
        </div>
      )}
      <form onSubmit={submit} className="grid grid-cols-2 gap-3">
        <Field label="Temp (°C)"><Input type="number" step="0.1" value={form.temperature_c || ""} onChange={(e) => setForm({ ...form, temperature_c: e.target.value })} /></Field>
        <Field label="Pulse (bpm)"><Input type="number" value={form.pulse_rate || ""} onChange={(e) => setForm({ ...form, pulse_rate: e.target.value })} /></Field>
        <Field label="BP systolic"><Input type="number" value={form.blood_pressure_systolic || ""} onChange={(e) => setForm({ ...form, blood_pressure_systolic: e.target.value })} /></Field>
        <Field label="BP diastolic"><Input type="number" value={form.blood_pressure_diastolic || ""} onChange={(e) => setForm({ ...form, blood_pressure_diastolic: e.target.value })} /></Field>
        <Field label="Resp. rate"><Input type="number" value={form.respiratory_rate || ""} onChange={(e) => setForm({ ...form, respiratory_rate: e.target.value })} /></Field>
        <Field label="SpO2 (%)"><Input type="number" value={form.spo2 || ""} onChange={(e) => setForm({ ...form, spo2: e.target.value })} /></Field>
        <Field label="Weight (kg)"><Input type="number" step="0.1" value={form.weight_kg || ""} onChange={(e) => setForm({ ...form, weight_kg: e.target.value })} /></Field>
        <Field label="Height (cm)"><Input type="number" value={form.height_cm || ""} onChange={(e) => setForm({ ...form, height_cm: e.target.value })} /></Field>
        <Button type="submit" className="col-span-2" disabled={saving}>{saving ? "Saving…" : "Record vitals"}</Button>
        {error && <p className="col-span-2 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}


function OrdersPanel({ encounterId, orders, onSaved }) {
  const [form, setForm] = useState({ order_type: "lab", details: "", target_department: "laboratory", priority: "routine" });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      await createWithOfflineFallback("order", "/orders", { encounter_id: Number(encounterId), ...form });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't place the order — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Orders</p>
      <div className="max-h-40 overflow-y-auto space-y-1 mb-3">
        {orders.length === 0 && <p className="text-sm text-ink/40">No orders placed.</p>}
        {orders.map((o) => (
          <div key={o.id} className="text-sm flex justify-between border border-line rounded-lg p-2">
            <span>{o.order_type}: {o.details}</span>
            <Badge tone="muted">{o.status}</Badge>
          </div>
        ))}
      </div>
      <form onSubmit={submit} className="space-y-2">
        <Select value={form.order_type} onChange={(e) => setForm({ ...form, order_type: e.target.value })}>
          <option value="lab">Laboratory (LOINC)</option>
          <option value="imaging">Imaging</option>
          <option value="procedure">Procedure</option>
          <option value="admission">Admission</option>
        </Select>
        <Input placeholder="Order details" value={form.details} onChange={(e) => setForm({ ...form, details: e.target.value })} />
        <Select value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })}>
          <option value="routine">Routine</option>
          <option value="urgent">Urgent</option>
          <option value="stat">Stat</option>
        </Select>
        <Button type="submit" disabled={saving}>{saving ? "Placing…" : "Place order"}</Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}

function ReferralPanel({ encounterId, currentDept, onSaved }) {
  const [to, setTo] = useState("laboratory");
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      await createWithOfflineFallback("referral", "/referrals", {
        encounter_id: Number(encounterId), to_department: to, reason,
      });
      setReason("");
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't send the referral — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Refer to another department</p>
      <p className="text-xs text-ink/50 mb-3">Currently in: <Badge tone="muted">{currentDept}</Badge></p>
      <form onSubmit={submit} className="space-y-2">
        <Select value={to} onChange={(e) => setTo(e.target.value)}>
          {DEPARTMENTS.map((d) => <option key={d} value={d}>{d}</option>)}
        </Select>
        <Textarea placeholder="Reason for referral" value={reason} onChange={(e) => setReason(e.target.value)} />
        <Button type="submit" disabled={saving}>{saving ? "Referring…" : "Refer patient"}</Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}

function PrescribePanel({ encounterId, onSaved }) {
  const [form, setForm] = useState({ drug_name: "", formulation: "", dose: "", route: "oral", frequency: "", duration: "" });
  const [alerts, setAlerts] = useState([]);
  const [saving, setSaving] = useState(false);
  const [stockNames, setStockNames] = useState([]);
  const [error, setError] = useState("");

  useEffect(() => {
    api.get("/pharmacy/stock").then((res) => setStockNames(res.data.map((s) => s.drug_name))).catch(() => {});
  }, []);

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      const res = await api.post("/pharmacy/prescriptions", { encounter_id: Number(encounterId), ...form });
      setAlerts(res.data.cds_alerts_list || []);
      setForm({ drug_name: "", formulation: "", dose: "", route: "oral", frequency: "", duration: "" });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't send the prescription — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Prescribe medication</p>
      <form onSubmit={submit} className="grid md:grid-cols-3 gap-3">
        <Field label="Drug name" required>
          <Input list="stock-drug-names" value={form.drug_name} onChange={(e) => setForm({ ...form, drug_name: e.target.value })} placeholder="Start typing…" />
          <datalist id="stock-drug-names">
            {stockNames.map((n) => <option key={n} value={n} />)}
          </datalist>
        </Field>
        <Field label="Formulation"><Input value={form.formulation} onChange={(e) => setForm({ ...form, formulation: e.target.value })} placeholder="e.g. tablet 500mg" /></Field>
        <Field label="Dose"><Input value={form.dose} onChange={(e) => setForm({ ...form, dose: e.target.value })} /></Field>
        <Field label="Route">
          <Select value={form.route} onChange={(e) => setForm({ ...form, route: e.target.value })}>
            <option value="oral">Oral</option><option value="IV">IV</option><option value="IM">IM</option>
            <option value="topical">Topical</option><option value="rectal">Rectal</option>
          </Select>
        </Field>
        <Field label="Frequency"><Input value={form.frequency} onChange={(e) => setForm({ ...form, frequency: e.target.value })} placeholder="e.g. twice daily" /></Field>
        <Field label="Duration"><Input value={form.duration} onChange={(e) => setForm({ ...form, duration: e.target.value })} placeholder="e.g. 5 days" /></Field>
        <Button type="submit" disabled={saving} className="md:col-span-3">{saving ? "Checking safety & sending…" : "Send prescription to pharmacy"}</Button>
      </form>
      {error && (
        <p className="mt-3 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>
      )}
      {alerts.length > 0 && (
        <div className="mt-3 space-y-1">
          {alerts.map((a, i) => (
            <p key={i} className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{a}</p>
          ))}
        </div>
      )}
    </Card>
  );
}

const WARDS = ["Male General", "Female General", "Pediatric", "Maternity", "Surgical", "Isolation"];

// Wards restricted to one sex — must mirror SEX_RESTRICTED_WARDS in WardController.
const SEX_RESTRICTED_WARDS = { "Male General": "male", "Female General": "female", Maternity: "female" };

function wardsForPatient(sex) {
  return WARDS.filter((w) => !SEX_RESTRICTED_WARDS[w] || !sex || SEX_RESTRICTED_WARDS[w] === sex);
}

function DispositionPanel({ encounterId, patientSex, onSaved, onClosed }) {
  const availableWards = wardsForPatient(patientSex);
  const [outcome, setOutcome] = useState("discharged");
  const [notes, setNotes] = useState("");
  const [ward, setWard] = useState(availableWards[0]);
  const [bed, setBed] = useState("");
  const [admissionDiagnosis, setAdmissionDiagnosis] = useState("");
  const [saving, setSaving] = useState(false);
  const [confirmDeath, setConfirmDeath] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    if (outcome === "died" && !confirmDeath) return;
    setSaving(true);
    setError("");
    try {
      if (outcome === "admitted") {
        await api.post("/wards/admit", { encounter_id: Number(encounterId), ward, bed, admission_diagnosis: admissionDiagnosis });
      }
      await api.post(`/encounters/${encounterId}/close`, { outcome, disposition_notes: notes });
      onSaved();
      onClosed();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't record disposition — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Close encounter / disposition</p>
      <form onSubmit={submit} className="space-y-3 w-full">
        <Select value={outcome} onChange={(e) => { setOutcome(e.target.value); setConfirmDeath(false); }}>
          <option value="discharged">Discharge home</option>
          <option value="admitted">Admit to ward</option>
          <option value="referred_out">Refer to another facility</option>
          <option value="died">Death documentation</option>
        </Select>
        {outcome === "admitted" && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <Select value={ward} onChange={(e) => setWard(e.target.value)}>
                {availableWards.map((w) => <option key={w} value={w}>{w}</option>)}
              </Select>
              <Input placeholder="Bed number" value={bed} onChange={(e) => setBed(e.target.value)} />
            </div>
            <Textarea placeholder="Admission diagnosis" value={admissionDiagnosis} onChange={(e) => setAdmissionDiagnosis(e.target.value)} />
            <p className="text-xs text-ink/45">
              Needs critical care instead? Admit to ICU/HDU from the <Link to="/icu" className="text-teal-600 dark:text-teal-300 underline">ICU module</Link> for ventilation, sedation, and sepsis tracking.
            </p>
          </div>
        )}
        <Textarea placeholder="Disposition notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
        {outcome === "died" && (
          <label className="flex items-center gap-2 text-sm text-alert">
            <input type="checkbox" checked={confirmDeath} onChange={(e) => setConfirmDeath(e.target.checked)} />
            I confirm this action documents a patient death and cannot be casually undone.
          </label>
        )}
        <Button
          type="submit"
          variant={outcome === "died" ? "danger" : "primary"}
          disabled={saving || (outcome === "died" && !confirmDeath)}
        >
          {saving ? "Saving…" : "Confirm disposition"}
        </Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}
