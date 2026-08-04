import { useEffect, useState, useCallback } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts";
import { ArrowRightLeft, Droplet, Pill as PillIcon, ChevronDown, ChevronUp } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, Field, Input, Select, Textarea, LoadingRow, priorityTone } from "../components/ui";
import PatientRibbon from "../components/PatientRibbon";
import ClinicalNoteForm from "../components/ClinicalNoteForm";
import { useAuth } from "../context/AuthContext";

const WARDS = ["Male General", "Female General", "Pediatric", "Maternity", "ICU/HDU", "Surgical", "Isolation"];

// Wards restricted to one sex — must mirror SEX_RESTRICTED_WARDS in WardController.
const SEX_RESTRICTED_WARDS = { "Male General": "male", "Female General": "female", Maternity: "female" };

function wardsForPatient(sex) {
  return WARDS.filter((w) => !SEX_RESTRICTED_WARDS[w] || !sex || SEX_RESTRICTED_WARDS[w] === sex);
}

export default function WardPatient() {
  const { encounterId } = useParams();
  const navigate = useNavigate();
  const { hasRole } = useAuth();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [msg, setMsg] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get(`/wards/patient/${encounterId}`);
      setData(res.data);
    } catch {
      // offline or not found
    } finally {
      setLoading(false);
    }
  }, [encounterId]);

  useEffect(() => { load(); }, [load]);

  function flash(text) {
    setMsg(text);
    setTimeout(() => setMsg(""), 3000);
  }

  if (loading) return <LoadingRow label="Loading ward chart…" />;
  if (!data) return <p className="text-sm text-ink/50">Chart unavailable — you may be offline.</p>;

  const vitalsChartData = data.vitals.map((v) => ({
    time: new Date(v.created_at).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" }),
    temp: v.temperature_c, pulse: v.pulse_rate, spo2: v.spo2,
  }));

  return (
    <div className="space-y-5 -mt-6 md:-mt-8">
      <PatientRibbon patient={data.patient} encounter={data} referral={data.referral} />
      {msg && <p className="text-sm text-moss bg-moss/10 border border-moss/20 rounded-lg px-3 py-2">{msg}</p>}

      <Card>
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div>
            <p className="font-display text-lg">{data.ward} · Bed {data.bed || "—"}</p>
            <p className="text-sm text-ink/60 mt-1">
              {data.admission_diagnosis || <span className="text-ink/35 italic">No admission diagnosis recorded</span>}
            </p>
          </div>
          <Badge tone="muted">{data.stage.replace("_", " ")}</Badge>
        </div>
      </Card>

      {hasRole("doctor", "nurse") && <TransferPanel encounterId={encounterId} currentWard={data.ward} currentBed={data.bed} patientSex={data.patient?.sex} onSaved={() => { load(); flash("Patient transferred"); }} />}

      <Card>
        <p className="font-display text-lg mb-3">Observation chart</p>
        {vitalsChartData.length === 0 ? (
          <p className="text-sm text-ink/40">No vitals recorded yet for this admission.</p>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <LineChart data={vitalsChartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-line))" />
              <XAxis dataKey="time" tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
              <YAxis tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
              <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
              <Line type="monotone" dataKey="temp" name="Temp °C" stroke="#C8443C" dot={{ r: 3 }} />
              <Line type="monotone" dataKey="pulse" name="Pulse" stroke="#0F4C4A" dot={{ r: 3 }} />
              <Line type="monotone" dataKey="spo2" name="SpO2 %" stroke="#3F8C84" dot={{ r: 3 }} />
            </LineChart>
          </ResponsiveContainer>
        )}
      </Card>

      <div className="grid md:grid-cols-2 gap-5">
        {hasRole("nurse", "doctor") && <FluidBalancePanel encounterId={encounterId} onSaved={() => { load(); flash("Fluid balance recorded"); }} />}
        <MedicationRecordPanel prescriptions={data.prescriptions} canAdminister={hasRole("nurse")} onSaved={() => { load(); flash("Medication administered"); }} />
      </div>

      {hasRole("doctor", "nurse") && (
        <ClinicalNoteForm
          encounterId={encounterId}
          notes={data.notes}
          canWrite
          title="Ward round notes, nursing care plans & procedure notes"
          allowedTypes={["progress", "nursing", "procedure", "discharge_summary"]}
          onSaved={() => { load(); flash("Note saved"); }}
        />
      )}

      {hasRole("doctor", "nurse") && (
        <DischargePanel encounterId={encounterId} onDone={() => navigate("/wards")} />
      )}
    </div>
  );
}

function TransferPanel({ encounterId, currentWard, currentBed, patientSex, onSaved }) {
  const availableWards = wardsForPatient(patientSex);
  const [ward, setWard] = useState(availableWards.includes(currentWard) ? currentWard : availableWards[0]);
  const [bed, setBed] = useState(currentBed || "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      await api.post("/wards/transfer", { encounter_id: Number(encounterId), ward, bed });
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't transfer the patient — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3 flex items-center gap-2"><ArrowRightLeft size={17} /> Transfer ward or bed</p>
      <form onSubmit={submit} className="flex flex-wrap gap-3 items-end">
        <Select value={ward} onChange={(e) => setWard(e.target.value)} className="max-w-xs">
          {availableWards.map((w) => <option key={w} value={w}>{w}</option>)}
        </Select>
        <Input placeholder="Bed number" value={bed} onChange={(e) => setBed(e.target.value)} className="max-w-[140px]" />
        <Button type="submit" size="sm" disabled={saving}>{saving ? "Transferring…" : "Transfer"}</Button>
      </form>
      {!patientSex && (
        <p className="text-xs text-ink/40 mt-2">Patient's sex isn't recorded, so all wards are shown — double-check before placing on a sex-specific ward.</p>
      )}
      {error && <p className="mt-2 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
    </Card>
  );
}

function FluidBalancePanel({ encounterId, onSaved }) {
  const [form, setForm] = useState({ direction: "intake", category: "oral", volume_ml: "", notes: "" });
  const [totals, setTotals] = useState(null);
  const [entries, setEntries] = useState([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  const load = useCallback(async () => {
    try {
      const res = await api.get(`/wards/fluid-balance/${encounterId}`);
      setTotals({ intake: res.data.intake_total_ml, output: res.data.output_total_ml, balance: res.data.balance_ml });
      setEntries(res.data.entries.slice(-5).reverse());
    } catch { /* offline */ }
  }, [encounterId]);

  useEffect(() => { load(); }, [load]);

  async function submit(e) {
    e.preventDefault();
    if (!form.volume_ml) return;
    setSaving(true);
    setError("");
    try {
      await api.post("/wards/fluid-balance", { encounter_id: Number(encounterId), ...form, volume_ml: Number(form.volume_ml) });
      setForm({ direction: "intake", category: "oral", volume_ml: "", notes: "" });
      load();
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save the entry — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3 flex items-center gap-2"><Droplet size={17} /> Fluid balance</p>
      {totals && (
        <div className="grid grid-cols-3 gap-2 text-center mb-3">
          <div className="bg-surface-alt rounded-lg py-2">
            <p className="text-xs text-ink/50">Intake</p>
            <p className="font-display text-lg">{totals.intake} ml</p>
          </div>
          <div className="bg-surface-alt rounded-lg py-2">
            <p className="text-xs text-ink/50">Output</p>
            <p className="font-display text-lg">{totals.output} ml</p>
          </div>
          <div className="bg-surface-alt rounded-lg py-2">
            <p className="text-xs text-ink/50">Balance</p>
            <p className={`font-display text-lg ${totals.balance < 0 ? "text-alert" : ""}`}>{totals.balance} ml</p>
          </div>
        </div>
      )}
      <form onSubmit={submit} className="grid grid-cols-2 gap-2 mb-3">
        <Select value={form.direction} onChange={(e) => setForm({ ...form, direction: e.target.value })}>
          <option value="intake">Intake</option>
          <option value="output">Output</option>
        </Select>
        <Select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
          {form.direction === "intake" ? (
            <><option value="oral">Oral</option><option value="IV fluids">IV fluids</option></>
          ) : (
            <><option value="urine">Urine</option><option value="vomitus">Vomitus</option><option value="drain">Drain</option><option value="stool">Stool</option></>
          )}
          <option value="other">Other</option>
        </Select>
        <Input type="number" min="0" placeholder="Volume (ml)" value={form.volume_ml} onChange={(e) => setForm({ ...form, volume_ml: e.target.value })} />
        <Input placeholder="Notes (optional)" value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
        <Button type="submit" size="sm" disabled={saving} className="col-span-2">{saving ? "Saving…" : "Add entry"}</Button>
      </form>
      {error && <p className="mb-3 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      {entries.length > 0 && (
        <ul className="text-xs text-ink/60 space-y-1">
          {entries.map((e) => (
            <li key={e.id} className="flex justify-between border-t border-line pt-1">
              <span>{e.direction === "intake" ? "+" : "−"} {e.category}</span>
              <span>{e.volume_ml} ml</span>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function MedicationRecordPanel({ prescriptions, canAdminister, onSaved }) {
  const [expandedId, setExpandedId] = useState(null);

  return (
    <Card>
      <p className="font-display text-lg mb-3 flex items-center gap-2"><PillIcon size={17} /> Medication administration record</p>
      {prescriptions.length === 0 ? (
        <p className="text-sm text-ink/40">No prescriptions for this admission yet.</p>
      ) : (
        <ul className="space-y-2">
          {prescriptions.map((rx) => (
            <MARRow
              key={rx.id}
              rx={rx}
              canAdminister={canAdminister}
              expanded={expandedId === rx.id}
              onToggle={() => setExpandedId(expandedId === rx.id ? null : rx.id)}
              onSaved={onSaved}
            />
          ))}
        </ul>
      )}
    </Card>
  );
}

function MARRow({ rx, canAdminister, expanded, onToggle, onSaved }) {
  const [showForm, setShowForm] = useState(false);
  const [doseGiven, setDoseGiven] = useState(rx.dose || "");
  const [notes, setNotes] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const administrations = rx.administrations || [];

  async function administer(e) {
    e.preventDefault();
    setSaving(true);
    setError("");
    try {
      await api.post(`/pharmacy/prescriptions/${rx.id}/administer`, { dose_given: doseGiven, notes });
      setShowForm(false);
      setNotes("");
      onSaved();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't record administration — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <li className="border border-line rounded-lg overflow-hidden">
      <div className="flex items-center justify-between text-sm p-2">
        <button onClick={onToggle} className="flex items-center gap-2 text-left min-w-0 flex-1">
          {expanded ? <ChevronUp size={14} className="text-ink/40 shrink-0" /> : <ChevronDown size={14} className="text-ink/40 shrink-0" />}
          <div className="min-w-0">
            <p className="font-medium truncate">{rx.drug_name} {rx.formulation}</p>
            <p className="text-xs text-ink/50">{rx.dose} · {rx.route} · {rx.frequency}
              {administrations.length > 0 && ` · given ${administrations.length}×`}
            </p>
          </div>
        </button>
        <div className="flex items-center gap-2 shrink-0">
          <Badge tone={rx.status === "dispensed" ? "success" : "muted"}>{rx.status}</Badge>
          {canAdminister && (
            <Button size="sm" variant="secondary" onClick={() => setShowForm((s) => !s)}>
              {showForm ? "Cancel" : "Give dose"}
            </Button>
          )}
        </div>
      </div>

      {showForm && (
        <form onSubmit={administer} className="px-2 pb-2 grid grid-cols-2 gap-2 border-t border-line pt-2">
          <Input placeholder="Dose given" value={doseGiven} onChange={(e) => setDoseGiven(e.target.value)} />
          <Input placeholder="Notes (optional)" value={notes} onChange={(e) => setNotes(e.target.value)} />
          <Button type="submit" size="sm" disabled={saving} className="col-span-2">
            {saving ? "Recording…" : "Confirm administration"}
          </Button>
          {error && <p className="col-span-2 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
        </form>
      )}

      {expanded && (
        <div className="px-2 pb-2 border-t border-line pt-2">
          {administrations.length === 0 ? (
            <p className="text-xs text-ink/40">Not administered yet.</p>
          ) : (
            <ul className="text-xs text-ink/60 space-y-1">
              {administrations.map((a) => (
                <li key={a.id} className="flex justify-between">
                  <span>{a.dose_given || "dose not specified"} by {a.administered_by_name || "unknown"}{a.notes ? ` — ${a.notes}` : ""}</span>
                  <span className="shrink-0 ml-2">{new Date(a.administered_at).toLocaleString()}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </li>
  );
}




function DischargePanel({ encounterId, onDone }) {
  const [outcome, setOutcome] = useState("discharged");
  const [notes, setNotes] = useState("");
  const [confirmDeath, setConfirmDeath] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  async function submit(e) {
    e.preventDefault();
    if (outcome === "died" && !confirmDeath) return;
    setSaving(true);
    setError("");
    try {
      await api.post(`/encounters/${encounterId}/close`, { outcome, disposition_notes: notes });
      onDone();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't complete discharge — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <Card>
      <p className="font-display text-lg mb-3">Discharge planning</p>
      <form onSubmit={submit} className="space-y-3 max-w-md">
        <Select value={outcome} onChange={(e) => { setOutcome(e.target.value); setConfirmDeath(false); }}>
          <option value="discharged">Discharge home</option>
          <option value="referred_out">Refer to another facility</option>
          <option value="died">Death documentation</option>
        </Select>
        <Textarea placeholder="Discharge summary / disposition notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
        {outcome === "died" && (
          <label className="flex items-center gap-2 text-sm text-alert">
            <input type="checkbox" checked={confirmDeath} onChange={(e) => setConfirmDeath(e.target.checked)} />
            I confirm this action documents a patient death and cannot be casually undone.
          </label>
        )}
        <Button type="submit" variant={outcome === "died" ? "danger" : "primary"} disabled={saving || (outcome === "died" && !confirmDeath)}>
          {saving ? "Saving…" : "Confirm discharge"}
        </Button>
        {error && <p className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
      </form>
    </Card>
  );
}
