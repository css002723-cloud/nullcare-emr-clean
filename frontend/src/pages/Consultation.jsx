import { useEffect, useState, useCallback } from "react";
import { Stethoscope, Siren } from "lucide-react";
import { useNavigate } from "react-router-dom";
import api from "../services/api";
import DepartmentQueue from "../components/DepartmentQueue";
import PageHeader from "../components/PageHeader";
import { LoadingRow, Card, Badge, Button } from "../components/ui";

export default function Consultation() {
  const [encounters, setEncounters] = useState([]);
  const [criticalResults, setCriticalResults] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null); // selected critical result for modal
  const [ackLoading, setAckLoading] = useState(false);
  const navigate = useNavigate();

  const emergencyEncounters = encounters.filter((enc) => enc.is_emergency);
  const regularEncounters = encounters.filter((enc) => !enc.is_emergency);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/encounters", { params: { department: "consultation" } }),
      api.get("/lab/critical-unacknowledged").catch(() => ({ data: [] })),
    ])
      .then(([encRes, critRes]) => {
        setEncounters(encRes.data);
        setCriticalResults(critRes.data || []);
      })
      .catch(() => setEncounters([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function acknowledge(resultId) {
    if (!resultId) return;
    setAckLoading(true);
    try {
      await api.post(`/lab/results/${resultId}/acknowledge-critical`);
      setSelected(null);
      load(); // refresh lists
    } catch (err) {
      alert(err.response?.data?.message || "Couldn't acknowledge — please try again.");
    } finally {
      setAckLoading(false);
    }
  }

  function openEncounter(encounterId) {
    if (encounterId) navigate(`/encounters/${encounterId}`);
  }

  return (
    <div className="space-y-5">
      <PageHeader icon={Stethoscope} title="Consultation queue" subtitle="Patients waiting to be seen or currently in consultation." />

      {criticalResults.length > 0 && (
        <Card className="border-alert/30 bg-alert/5">
          <p className="font-semibold text-alert flex items-center gap-2">
            <Badge tone="critical">Critical</Badge> Unacknowledged critical lab results
          </p>

          <div className="mt-2 space-y-2">
            <p className="text-sm text-ink/60">
              {criticalResults.length} critical result{criticalResults.length > 1 ? "s" : ""} need clinician acknowledgement.
            </p>

            <div className="grid gap-2 mt-2">
              {criticalResults.map((r) => (
                <button
                  key={r.id}
                  onClick={() => setSelected(r)}
                  className="text-left rounded-lg p-3 bg-surface-alt border border-line hover:bg-surface cursor-pointer"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-semibold text-sm">
                        {r.patient_name ? `${r.patient_name} — ` : ""}{r.lab_order?.loinc_display || r.lab_order?.test_code || "Lab result"}
                      </p>
                      <p className="text-xs text-ink/50 mrn-mono">
                        {r.lab_order ? `MRN/visit id: ${r.lab_order.encounter_id}` : ""}
                      </p>
                    </div>
                    <Badge tone="critical">Result: {r.result_value}{r.unit ? ` ${r.unit}` : ""}</Badge>
                  </div>
                </button>
              ))}
            </div>

            <p className="text-sm text-ink/60 mt-2">
              Click a result to review details, acknowledge it, or open the encounter.
            </p>
          </div>
        </Card>
      )}

      <Card className={emergencyEncounters.length > 0 ? "border-alert/30 bg-alert/5" : "border-line bg-surface-alt"}>
        <div className="flex flex-col gap-2">
          <p className={emergencyEncounters.length > 0 ? "font-semibold text-alert flex items-center gap-2" : "font-semibold text-ink/80 flex items-center gap-2"}>
            <Badge tone={emergencyEncounters.length > 0 ? "critical" : "muted"}>
              {emergencyEncounters.length > 0 ? "Emergency" : "No emergencies"}
            </Badge>
            {emergencyEncounters.length > 0
              ? `There ${emergencyEncounters.length === 1 ? "is" : "are"} ${emergencyEncounters.length} emergency patient${emergencyEncounters.length === 1 ? "" : "s"} currently waiting for consultation.`
              : "No emergency patients are currently waiting in consultation."}
          </p>

          {emergencyEncounters.length > 0 && (
            <div className="mt-3 grid gap-2">
              {emergencyEncounters.map((enc) => (
                <button
                  key={enc.id}
                  onClick={() => openEncounter(enc.id)}
                  className="text-left rounded-lg p-3 bg-surface border border-alert/20 hover:bg-alert/10 transition"
                >
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <p className="font-semibold text-sm text-alert">{enc.patient?.full_name || "Unknown patient"}</p>
                      <p className="text-xs text-ink/50 mrn-mono">{enc.mrn}</p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-alert/10 text-alert border border-alert/30 px-2 py-0.5 text-[10px] uppercase font-semibold">
                      <Siren size={12} /> Emergency
                    </span>
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>
      </Card>

      {loading ? <LoadingRow /> : (
        <DepartmentQueue
          title="consultation"
          encounters={regularEncounters}
          emptyHint="Patients move here automatically once nursing triage and vitals are complete."
        />
      )}

      {selected && (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
          <div className="absolute inset-0 bg-black/40" onClick={() => setSelected(null)} />
          <div className="relative max-w-lg w-full p-6 bg-surface border border-line rounded-lg shadow-lg z-10">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="font-display text-lg">
                  {selected.patient_name || "Unknown patient"} — {selected.lab_order?.loinc_display || selected.lab_order?.test_code}
                </h3>
                <p className="text-xs text-ink/50 mrn-mono">Barcode: {selected.lab_order?.barcode || "n/a"}</p>
              </div>
              <button className="text-sm text-ink/50" onClick={() => setSelected(null)}>Close</button>
            </div>

            <div className="mt-4 text-sm text-ink/70 space-y-2">
              <div>
                <div className="text-ink/60">Result</div>
                <div className="font-semibold text-lg">{selected.result_value} {selected.unit || ""}</div>
                {selected.reference_range && <div className="text-xs text-ink/50">Reference: {selected.reference_range}</div>}
              </div>

              {selected.interpretation && (
                <div>
                  <div className="text-ink/60">Interpretation</div>
                  <div className="text-sm">{selected.interpretation}</div>
                </div>
              )}

              <div>
                <div className="text-ink/60">Critical alert acknowledged</div>
                <div className="text-sm">{selected.critical_alert_acknowledged ? "Yes" : "No"}</div>
              </div>
            </div>

            <div className="mt-5 flex gap-3 justify-end">
              <Button size="sm" variant="secondary" onClick={() => openEncounter(selected.lab_order?.encounter_id)}>Open encounter</Button>
              <Button size="sm" onClick={() => acknowledge(selected.id)} disabled={ackLoading}>
                {ackLoading ? "Acknowledging…" : "Acknowledge"}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
