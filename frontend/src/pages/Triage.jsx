import { useEffect, useState, useCallback } from "react";
import { Activity } from "lucide-react";
import api from "../services/api";
import DepartmentQueue from "../components/DepartmentQueue";
import PageHeader from "../components/PageHeader";
import { LoadingRow } from "../components/ui";

export default function Triage() {
  const [encounters, setEncounters] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/encounters", { params: { department: "triage" } })
      .then((res) => setEncounters(res.data))
      .catch(() => setEncounters([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  return (
    <div className="space-y-5">
      <PageHeader
        icon={Activity}
        title="Triage & nursing queue"
        subtitle="Patients waiting for triage vitals and nursing assessment. Open a patient to record vitals — this automatically moves them to the consultation queue."
      />
      {loading ? <LoadingRow /> : (
        <DepartmentQueue
          title="triage"
          encounters={encounters}
          emptyHint="New arrivals from reception will appear here."
        />
      )}
    </div>
  );
}
