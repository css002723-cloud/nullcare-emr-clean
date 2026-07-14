import { useEffect, useState, useCallback } from "react";
import { BedDouble } from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function Wards() {
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
      <PageHeader icon={BedDouble} title="Wards & bed occupancy" subtitle={`${totalBeds} patients currently admitted across all wards.`} />

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
                  <li key={p.encounter_id} className="flex justify-between border-t border-line py-1.5">
                    <span>{p.patient_name}</span>
                    <span className="text-ink/50 mrn-mono">{p.mrn} · Bed {p.bed || "—"}</span>
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
