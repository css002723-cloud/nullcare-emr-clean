import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { FileDown } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, LoadingRow, Field, Input, Select, priorityTone, calcAge } from "../components/ui";
import PatientRibbon from "../components/PatientRibbon";

export default function PatientDetail() {
  const { uid } = useParams();
  const navigate = useNavigate();
  const [patient, setPatient] = useState(null);
  const [encounters, setEncounters] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showAllergyForm, setShowAllergyForm] = useState(false);
  const [allergy, setAllergy] = useState({ substance: "", reaction: "", severity: "mild" });
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState("");

  async function load() {
    setLoading(true);
    try {
      const pRes = await api.get(`/patients/by-uid/${uid}`);
      setPatient(pRes.data);
      const hRes = await api.get(`/patients/${pRes.data.id}/history`);
      setEncounters(hRes.data);
    } catch {
      // offline with nothing cached — leave blank, page will show empty state
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => { load(); }, [uid]);

  async function addAllergy(e) {
    e.preventDefault();
    if (!allergy.substance || !patient) return;
    setError("");
    try {
      await api.post(`/patients/${patient.id}/allergies`, allergy);
      setAllergy({ substance: "", reaction: "", severity: "mild" });
      setShowAllergyForm(false);
      load();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't save the allergy — please try again. This is safety-critical, so don't assume it was recorded.");
    }
  }

  async function exportRecord() {
    if (!patient) return;
    setExporting(true);
    setError("");
    try {
      const res = await api.get(`/patients/${patient.id}/export`, { responseType: "blob" });
      const url = URL.createObjectURL(new Blob([res.data], { type: "application/pdf" }));
      const a = document.createElement("a");
      a.href = url;
      a.download = `nullcare-${patient.patient_uid}-full-record.pdf`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      setError("Couldn't export the record — please try again.");
    } finally {
      setExporting(false);
    }
  }

  if (loading) return <LoadingRow label="Loading patient record…" />;
  if (!patient) return <p className="text-sm text-ink/50">Patient record unavailable — you may be offline.</p>;

  return (
    <div className="space-y-5 -mt-6 md:-mt-8">
      <PatientRibbon
        patient={patient}
        extra={
          <Button size="sm" variant="secondary" icon={FileDown} onClick={exportRecord} disabled={exporting}>
            {exporting ? "Preparing…" : "Export full record"}
          </Button>
        }
      />

      <div className="grid md:grid-cols-3 gap-5">
        <Card className="md:col-span-2">
          <div className="flex items-center justify-between mb-3">
            <p className="font-display text-lg">Demographics</p>
          </div>
          <dl className="grid grid-cols-2 gap-y-2 text-sm">
            <dt className="text-ink/50">Patient ID (permanent)</dt><dd className="mrn-mono">{patient.patient_uid}</dd>
            <dt className="text-ink/50">National ID</dt><dd>{patient.national_id || "—"}</dd>
            <dt className="text-ink/50">Phone</dt><dd>{patient.phone || "—"}</dd>
            <dt className="text-ink/50">Village / TA</dt><dd>{patient.village || "—"} / {patient.traditional_authority || "—"}</dd>
            <dt className="text-ink/50">District / Region</dt><dd>{patient.district || "—"} / {patient.region || "—"}</dd>
            <dt className="text-ink/50">Occupation</dt><dd>{patient.occupation || "—"}</dd>
            <dt className="text-ink/50">Guardian</dt><dd>{patient.guardian_name ? `${patient.guardian_name} (${patient.guardian_relationship || "n/a"})` : "—"}</dd>
            <dt className="text-ink/50">Category</dt><dd><Badge>{patient.patient_category}</Badge></dd>
            <dt className="text-ink/50">Consent — research</dt><dd>{patient.consent_research ? "Given" : "Not given"}</dd>
          </dl>
        </Card>

        <Card>
          <div className="flex items-center justify-between mb-3">
            <p className="font-display text-lg">Allergies</p>
            <Button size="sm" variant="ghost" onClick={() => setShowAllergyForm((s) => !s)}>
              + Add
            </Button>
          </div>
          {patient.allergies?.length ? (
            <ul className="space-y-2">
              {patient.allergies.map((a) => (
                <li key={a.id} className="text-sm border border-line rounded-lg p-2">
                  <span className="font-semibold">{a.substance}</span>{" "}
                  <Badge tone={a.severity === "severe" ? "critical" : "warning"}>{a.severity}</Badge>
                  {a.reaction && <p className="text-xs text-ink/50 mt-1">{a.reaction}</p>}
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-ink/40">No known allergies recorded.</p>
          )}
          {showAllergyForm && (
            <form onSubmit={addAllergy} className="mt-3 space-y-2 border-t border-line pt-3">
              <Field label="Substance" required>
                <Input value={allergy.substance} onChange={(e) => setAllergy({ ...allergy, substance: e.target.value })} />
              </Field>
              <Field label="Reaction">
                <Input value={allergy.reaction} onChange={(e) => setAllergy({ ...allergy, reaction: e.target.value })} />
              </Field>
              <Field label="Severity">
                <Select value={allergy.severity} onChange={(e) => setAllergy({ ...allergy, severity: e.target.value })}>
                  <option value="mild">Mild</option>
                  <option value="moderate">Moderate</option>
                  <option value="severe">Severe</option>
                </Select>
              </Field>
              <Button size="sm" type="submit">Save allergy</Button>
            </form>
          )}
          {error && <p className="mt-2 text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{error}</p>}
        </Card>
      </div>

      <Card>
        <p className="font-display text-lg mb-3">Visit history</p>
        {encounters.length === 0 ? (
          <p className="text-sm text-ink/40">No visits recorded yet.</p>
        ) : (
          <div className="divide-y divide-line">
            {encounters.map((e) => (
              <div
                key={e.id}
                className="py-3 cursor-pointer hover:bg-surface-alt/60 -mx-2 px-2 rounded"
                onClick={() => navigate(`/encounters/${e.id}`)}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium mrn-mono">{e.mrn}</p>
                    <p className="text-xs text-ink/50">{e.chief_complaint || "No chief complaint recorded"}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    {e.is_emergency && <Badge tone="critical">emergency</Badge>}
                    <Badge tone={priorityTone(e.priority)}>{e.priority}</Badge>
                    <Badge tone="muted">{e.stage.replace("_", " ")}</Badge>
                  </div>
                </div>
                {e.referral && (
                  <p className="text-xs text-teal-700 dark:text-teal-300 bg-surface-alt rounded-lg px-2.5 py-1.5 mt-2">
                    Currently with <span className="font-semibold">{e.referral.to_department}</span>
                    {e.referral.referred_by_name && <> · referred by <span className="font-semibold">{e.referral.referred_by_name}</span></>}
                    {e.referral.message && <>: "{e.referral.message}"</>}
                  </p>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
