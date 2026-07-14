import { useEffect, useState, useCallback } from "react";
import { Stethoscope } from "lucide-react";
import api from "../services/api";
import DepartmentQueue from "../components/DepartmentQueue";
import PageHeader from "../components/PageHeader";
import { LoadingRow, Card, Badge } from "../components/ui";

export default function Consultation() {
  const [encounters, setEncounters] = useState([]);
  const [criticalResults, setCriticalResults] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([
      api.get("/encounters", { params: { department: "consultation" } }),
      api.get("/lab/critical-unacknowledged").catch(() => ({ data: [] })),
    ])
      .then(([encRes, critRes]) => {
        setEncounters(encRes.data);
        setCriticalResults(critRes.data);
      })
      .catch(() => setEncounters([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader icon={Stethoscope} title="Consultation queue" subtitle="Patients waiting to be seen or currently in consultation." />

      {criticalResults.length > 0 && (
        <Card className="border-alert/30 bg-alert/5">
          <p className="font-semibold text-alert flex items-center gap-2">
            <Badge tone="critical">Critical</Badge> Unacknowledged critical lab results
          </p>
          <p className="text-sm text-ink/60 mt-1">
            {criticalResults.length} critical result{criticalResults.length > 1 ? "s" : ""} need clinician acknowledgement.
            Open the relevant patient's laboratory tab to review and acknowledge.
          </p>
        </Card>
      )}

      {loading ? <LoadingRow /> : (
        <DepartmentQueue
          title="consultation"
          encounters={encounters}
          emptyHint="Patients move here automatically once nursing triage and vitals are complete."
        />
      )}
    </div>
  );
}
