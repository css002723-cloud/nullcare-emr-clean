import { useEffect, useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { HeartPulse, AlertTriangle, ChevronRight, Skull } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";
import PatientLookup from "../components/PatientLookup";
import { useAuth } from "../context/AuthContext";

export default function ICU() {
  const { hasRole } = useAuth();
  const navigate = useNavigate();
  const [patients, setPatients] = useState([]);
  const [alerts, setAlerts] = useState(null);
  const [dashboard, setDashboard] = useState(null);
  const [loading, setLoading] = useState(true);
  const [showAdmit, setShowAdmit] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/icu/patients"),
      api.get("/icu/critical-alerts").catch(() => ({ data: { critical_labs: [], critical_imaging: [], sepsis_alerts: [] } })),
      api.get("/icu/dashboard").catch(() => ({ data: null })),
    ]).then(([pRes, aRes, dRes]) => {
      setPatients(pRes.data);
      setAlerts(aRes.data);
      setDashboard(dRes.data);
    }).catch(() => setPatients([])).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  const alertCount = alerts ? alerts.critical_labs.length + alerts.critical_imaging.length + alerts.sepsis_alerts.length : 0;

  return (
    <div className="space-y-5">
      <PageHeader
        icon={HeartPulse}
        title="Intensive Care / HDU"
        subtitle="Continuous observation, critical care documentation, and mortality/morbidity review for ICU/HDU patients."
        action={hasRole("doctor", "nurse") && <Button onClick={() => setShowAdmit((s) => !s)}>{showAdmit ? "Cancel" : "Admit to ICU/HDU"}</Button>}
      />

      {showAdmit && <AdmitPanel onAdmitted={() => { setShowAdmit(false); load(); }} />}

      {alertCount > 0 && (
        <Card className="border-alert/30 bg-alert/5">
          <p className="font-semibold text-alert flex items-center gap-2 mb-2">
            <AlertTriangle size={16} /> {alertCount} critical alert{alertCount > 1 ? "s" : ""} in ICU/HDU
          </p>
          <ul className="text-sm space-y-1">
            {alerts.critical_labs.map((l) => (
              <li key={`lab-${l.id}`}>Critical lab result — {l.patient_name} ({l.mrn}): {l.interpretation || l.result_value}</li>
            ))}
            {alerts.critical_imaging.map((im) => (
              <li key={`img-${im.id}`}>Critical imaging finding — {im.patient_name} ({im.mrn}): {im.impression}</li>
            ))}
            {alerts.sepsis_alerts.map((s) => (
              <li key={`sep-${s.id}`} className="flex items-center gap-1"><Skull size={13} /> Sepsis alert — {s.patient_name} ({s.mrn})</li>
            ))}
          </ul>
        </Card>
      )}

      {dashboard && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <Card className="p-4"><p className="text-xs text-ink/50">Currently admitted</p><p className="font-display text-2xl">{dashboard.currently_admitted}</p></Card>
          <Card className="p-4"><p className="text-xs text-ink/50">Total ICU admissions</p><p className="font-display text-2xl">{dashboard.total_icu_admissions}</p></Card>
          <Card className="p-4"><p className="text-xs text-ink/50">Mortality rate</p><p className="font-display text-2xl">{dashboard.mortality_rate_pct}%</p></Card>
          <Card className="p-4"><p className="text-xs text-ink/50">Avg. length of stay</p><p className="font-display text-2xl">{dashboard.avg_length_of_stay_hours ?? "—"}{dashboard.avg_length_of_stay_hours != null && "h"}</p></Card>
        </div>
      )}

      {loading ? <LoadingRow /> : patients.length === 0 ? (
        <EmptyState icon={HeartPulse} title="No patients currently in ICU/HDU" hint="Admit a patient above to start critical care tracking." />
      ) : (
        <div className="space-y-3">
          {patients.map((p) => (
            <Card
              key={p.id}
              className="cursor-pointer hover:border-teal-300 transition-colors"
              role="button"
              tabIndex={0}
              onClick={() => navigate(`/icu/${p.id}`)}
              onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                  e.preventDefault();
                  navigate(`/icu/${p.id}`);
                }
              }}
            >
              <div className="flex items-center justify-between flex-wrap gap-2">
                <div className="flex items-start gap-3">
                  <div className="flex h-8 w-8 items-center justify-center rounded-full bg-teal-50 text-sm font-semibold text-teal-700">
                    {patients.findIndex((item) => item.id === p.id) + 1}
                  </div>
                  <div>
                    <p className="font-semibold">{p.patient?.full_name || p.patient?.given_name && p.patient?.family_name ? `${p.patient.given_name} ${p.patient.family_name}` : p.patient?.name || "Unnamed patient"}</p>
                    <p className="text-xs text-ink/50 mrn-mono">{p.mrn} · Bed {p.bed || "—"}</p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {p.sepsis_alert && <Badge tone="critical" icon={Skull}>Sepsis alert</Badge>}
                  {p.latest_ews !== null && p.latest_ews >= 5 && <Badge tone="critical">EWS {p.latest_ews}</Badge>}
                  {p.latest_note && <Badge tone="muted">{p.latest_note.ventilation_status || "no ventilation"}</Badge>}
                  <ChevronRight size={16} className="text-ink/30" />
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

function AdmitPanel({ onAdmitted }) {
  const [encounterId, setEncounterId] = useState(null);
  const [form, setForm] = useState({
    bed: "", admission_diagnosis: "", ventilation_status: "none", oxygen_therapy: "",
    sedation_assessment: "", inotropes: "", sepsis_alert: false, admission_note: "",
  });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [resetKey, setResetKey] = useState(0);

  async function submit(e) {
    e.preventDefault();
    setError("");
    if (!encounterId) {
      setError("Select the patient's active visit before admitting to ICU/HDU.");
      return;
    }
    setSaving(true);
    try {
      await api.post("/icu/admit", { encounter_id: encounterId, ...form });
      setEncounterId(null);
      setForm({ bed: "", admission_diagnosis: "", ventilation_status: "none", oxygen_therapy: "", sedation_assessment: "", inotropes: "", sepsis_alert: false, admission_note: "" });
      setResetKey((k) => k + 1);
      onAdmitted();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't admit this patient — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card className="bg-surface-alt border-line">
      <p className="font-display text-lg mb-3">ICU / HDU admission</p>
      <form onSubmit={submit} className="grid md:grid-cols-2 gap-3">
        <div className="md:col-span-2">
          <PatientLookup key={resetKey} requireEncounter label="Patient" onSelect={({ encounterId }) => setEncounterId(encounterId)} />
        </div>
        <Field label="Bed"><Input value={form.bed} onChange={(e) => setForm({ ...form, bed: e.target.value })} /></Field>
        <Field label="Ventilation status">
          <Select value={form.ventilation_status} onChange={(e) => setForm({ ...form, ventilation_status: e.target.value })}>
            <option value="none">None</option>
            <option value="non_invasive">Non-invasive</option>
            <option value="invasive">Invasive</option>
          </Select>
        </Field>
        <Field label="Oxygen therapy"><Input value={form.oxygen_therapy} onChange={(e) => setForm({ ...form, oxygen_therapy: e.target.value })} placeholder="e.g. 4L nasal cannula" /></Field>
        <Field label="Sedation assessment"><Input value={form.sedation_assessment} onChange={(e) => setForm({ ...form, sedation_assessment: e.target.value })} placeholder="e.g. RASS -2" /></Field>
        <Field label="Inotropes / vasopressors" className="md:col-span-2"><Input value={form.inotropes} onChange={(e) => setForm({ ...form, inotropes: e.target.value })} placeholder="e.g. Noradrenaline 0.1mcg/kg/min" /></Field>
        <Field label="Admission diagnosis" className="md:col-span-2"><Textarea value={form.admission_diagnosis} onChange={(e) => setForm({ ...form, admission_diagnosis: e.target.value })} /></Field>
        <Field label="Admission note" className="md:col-span-2"><Textarea value={form.admission_note} onChange={(e) => setForm({ ...form, admission_note: e.target.value })} /></Field>
        <label className="flex items-center gap-2 text-sm text-alert md:col-span-2">
          <input type="checkbox" checked={form.sepsis_alert} onChange={(e) => setForm({ ...form, sepsis_alert: e.target.checked })} />
          Sepsis alert — flag for urgent review
        </label>
        {error && <p className="text-sm text-alert md:col-span-2">{error}</p>}
        <Button type="submit" disabled={saving} className="md:col-span-2">{saving ? "Admitting…" : "Admit to ICU/HDU"}</Button>
      </form>
    </Card>
  );
}
