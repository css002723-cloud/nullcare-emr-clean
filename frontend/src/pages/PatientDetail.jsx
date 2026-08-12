import { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import { FileDown, ClipboardPlus, LogIn } from "lucide-react";
import api from "../services/api";
import { Card, Badge, Button, LoadingRow, Field, Input, Select, priorityTone, calcAge } from "../components/ui";
import PatientRibbon from "../components/PatientRibbon";
import { useAuth } from "../context/AuthContext";

const CLOSED_STAGES = ["discharged", "closed", "deceased"];

export default function PatientDetail() {
  const { hasRole } = useAuth();
  const { uid } = useParams();
  const navigate = useNavigate();
  const [patient, setPatient] = useState(null);
  const [encounters, setEncounters] = useState([]);
  const [labOrders, setLabOrders] = useState([]);
  const [imagingOrders, setImagingOrders] = useState([]);
  const [prescriptions, setPrescriptions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showAllergyForm, setShowAllergyForm] = useState(false);
  const [allergy, setAllergy] = useState({ substance: "", reaction: "", severity: "mild" });
  const [exporting, setExporting] = useState(false);
  const [startingVisit, setStartingVisit] = useState(false);
  const [error, setError] = useState("");

  async function load() {
    setLoading(true);
    try {
      const pRes = await api.get(`/patients/by-uid/${uid}`);
      setPatient(pRes.data);
      const hRes = await api.get(`/patients/${pRes.data.id}/history`);
      setEncounters(hRes.data);
      if (hasRole("doctor")) {
        const [labsRes, imagingRes, prescriptionsRes] = await Promise.all([
          api.get("/lab/orders", { params: { patient_id: pRes.data.id } }),
          api.get("/imaging/orders", { params: { patient_id: pRes.data.id } }),
          api.get("/pharmacy/prescriptions", { params: { patient_id: pRes.data.id } }),
        ]);
        setLabOrders(labsRes.data);
        setImagingOrders(imagingRes.data);
        setPrescriptions(prescriptionsRes.data);
      } else {
        setLabOrders([]);
        setImagingOrders([]);
        setPrescriptions([]);
      }
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

  async function startNewVisit() {
    if (!patient) return;
    setStartingVisit(true);
    setError("");
    try {
      const res = await api.post("/encounters", { patient_id: patient.id, visit_type: "outpatient" });
      navigate(`/encounters/${res.data.id}`);
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't start a new visit — please try again.");
    } finally {
      setStartingVisit(false);
    }
  }

  if (loading) return <LoadingRow label="Loading patient record…" />;
  if (!patient) return <p className="text-sm text-ink/50">Patient record unavailable — you may be offline.</p>;

  const activeEncounter = encounters.find((e) => !CLOSED_STAGES.includes(e.stage));

  return (
    <div className="space-y-5 -mt-6 md:-mt-8">
      <PatientRibbon
        patient={patient}
        extra={
          <div className="flex flex-wrap gap-2">
            {hasRole("reception", "nurse", "admin") && (
              activeEncounter ? (
                <Button size="sm" icon={LogIn} onClick={() => navigate(`/encounters/${activeEncounter.id}`)}>
                  Open current visit
                </Button>
              ) : (
                <Button size="sm" icon={ClipboardPlus} onClick={startNewVisit} disabled={startingVisit}>
                  {startingVisit ? "Starting…" : "Start new visit → triage"}
                </Button>
              )
            )}
            <Button size="sm" variant="secondary" icon={FileDown} onClick={exportRecord} disabled={exporting}>
              {exporting ? "Preparing…" : "Export full record"}
            </Button>
          </div>
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

      {hasRole("doctor") && (
        <Card>
          <div className="flex items-center justify-between mb-3">
            <p className="font-display text-lg">Laboratory and imaging</p>
            <p className="text-xs text-ink/50">Only doctors can view these results here.</p>
          </div>

          <div className="space-y-4">
            <div>
              <p className="font-semibold mb-2">Laboratory results</p>
              {labOrders.length === 0 ? (
                <p className="text-sm text-ink/40">No lab orders found for this patient.</p>
              ) : (
                <div className="space-y-3">
                  {labOrders.map((order) => (
                    <div key={order.id} className="rounded-2xl border border-line p-4 bg-surface-alt">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold">{order.loinc_display || order.test_code}</p>
                          <p className="text-xs text-ink/50 mrn-mono">LOINC {order.loinc_code || "n/a"} · barcode {order.barcode}</p>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge tone={order.status === "resulted" ? "success" : "muted"}>{order.status}</Badge>
                          {order.result?.is_critical && <Badge tone="critical">Critical</Badge>}
                          {order.result?.is_abnormal && !order.result?.is_critical && <Badge tone="warning">Abnormal</Badge>}
                        </div>
                      </div>
                      {order.result ? (
                        <div className="mt-3 grid gap-2 text-sm text-ink/70">
                          <p><span className="font-semibold text-ink">Result:</span> {order.result.result_value} {order.result.unit || ""}</p>
                          {order.result.reference_range && <p><span className="font-semibold text-ink">Reference range:</span> {order.result.reference_range}</p>}
                          {order.result.interpretation && <p><span className="font-semibold text-ink">Interpretation:</span> {order.result.interpretation}</p>}
                        </div>
                      ) : (
                        <p className="mt-3 text-sm text-ink/50">No result recorded yet for this test.</p>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>

            <div>
              <p className="font-semibold mb-2">Imaging results</p>
              {imagingOrders.length === 0 ? (
                <p className="text-sm text-ink/40">No imaging studies found for this patient.</p>
              ) : (
                <div className="space-y-3">
                  {imagingOrders.map((order) => (
                    <div key={order.id} className="rounded-2xl border border-line p-4 bg-surface-alt">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p className="text-sm font-semibold">{order.modality} — {order.study_description}</p>
                          <p className="text-xs text-ink/50 mrn-mono">Accession {order.accession_number}</p>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge tone={order.status === "reported" ? "success" : "muted"}>{order.status}</Badge>
                        </div>
                      </div>
                      {order.report ? (
                        <div className="mt-3 text-sm text-ink/70 space-y-2">
                          <p><span className="font-semibold text-ink">Findings:</span> {order.report.findings || "—"}</p>
                          <p><span className="font-semibold text-ink">Impression:</span> {order.report.impression || "—"}</p>
                          {order.report.is_critical_finding && <Badge tone="critical">Critical finding</Badge>}
                        </div>
                      ) : (
                        <p className="mt-3 text-sm text-ink/50">No report written yet for this imaging order.</p>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </Card>
      )}

      {hasRole("doctor") && (
        <Card>
          <div className="flex items-center justify-between mb-3">
            <p className="font-display text-lg">Prescribed medications</p>
            <p className="text-xs text-ink/50">Only doctors can view prescriptions here.</p>
          </div>
          {prescriptions.length === 0 ? (
            <p className="text-sm text-ink/40">No prescriptions found for this patient.</p>
          ) : (
            <div className="space-y-3">
              {prescriptions.map((rx) => (
                <div key={rx.id} className="rounded-2xl border border-line p-4 bg-surface-alt">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold">{rx.drug_name}{rx.formulation ? ` — ${rx.formulation}` : ''}</p>
                      <p className="text-xs text-ink/50 mrn-mono">{rx.dose || 'Dose not specified'} · {rx.route || 'Route not specified'} · {rx.frequency || 'Frequency not specified'} · {rx.duration || 'Duration not specified'}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge tone={rx.status === 'dispensed' ? 'success' : 'muted'}>{rx.status}</Badge>
                    </div>
                  </div>
                  <div className="mt-3 text-sm text-ink/70 space-y-1">
                    {rx.prescribed_by_name && <p><span className="font-semibold text-ink">Prescribed by:</span> {rx.prescribed_by_name}</p>}
                    {rx.cds_alerts && (() => {
                      try {
                        const alerts = JSON.parse(rx.cds_alerts);
                        return Array.isArray(alerts) && alerts.length > 0 ? (
                          <div className="space-y-1">
                            <p className="font-semibold text-ink">Safety alerts:</p>
                            {alerts.map((alert, i) => (
                              <p key={i} className="text-xs text-alert bg-alert/5 border border-alert/20 rounded px-2 py-1">{alert}</p>
                            ))}
                          </div>
                        ) : null;
                      } catch {
                        return <p><span className="font-semibold text-ink">Safety alerts:</span> {rx.cds_alerts}</p>;
                      }
                    })()}
                  </div>
                </div>
              ))}
            </div>
          )}
        </Card>
      )}

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
