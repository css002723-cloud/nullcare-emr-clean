import { useEffect, useState, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { BedDouble, ChevronRight } from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function Wards() {
  const navigate = useNavigate();
  const [occupancy, setOccupancy] = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    api.get("/wards/occupancy").then((res) => setOccupancy(res.data)).catch(() => setOccupancy([])).finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  const totalBeds = occupancy.reduce((sum, w) => sum + w.occupied_beds, 0);

  return (
    <div className="space-y-5">
      <PageHeader icon={BedDouble} title="Wards & bed occupancy" subtitle={`${totalBeds} patients currently admitted across general wards.`} />

      {loading ? <LoadingRow /> : occupancy.length === 0 ? (
        <EmptyState title="No patients currently admitted" hint="Admit a patient from their encounter page to see them appear here." />
      ) : (
        <div className="grid md:grid-cols-2 gap-4">
          {occupancy.map((w) => (
            <Card key={w.ward}>
              <div className="flex items-center justify-between mb-2">
                <p className="font-display text-lg">{w.ward}</p>
                <Badge>{w.occupied_beds} occupied</Badge>
              </div>
              <ul className="space-y-1 text-sm">
                {w.patients.map((p) => (
                  <li
                    key={p.encounter_id}
                    className="flex items-center justify-between border-t border-line py-2 cursor-pointer hover:bg-surface-alt/60 -mx-2 px-2 rounded"
                    onClick={() => navigate(`/wards/${p.encounter_id}`)}
                  >
                    <div>
                      <p className="font-medium">{p.patient_name}</p>
                      {p.admission_diagnosis && <p className="text-xs text-ink/45">{p.admission_diagnosis}</p>}
                    </div>
                    <div className="flex items-center gap-2 text-ink/50">
                      <span className="mrn-mono text-xs">{p.mrn} · Bed {p.bed || "—"}</span>
                      <ChevronRight size={14} />
                    </div>
                  </li>
                ))}
              </ul>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
