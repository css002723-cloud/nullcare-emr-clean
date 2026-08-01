import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { Users, Search, UserPlus } from "lucide-react";
import api from "../services/api";
import { Card, Input, Button, Badge, EmptyState, LoadingRow, calcAge } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { getWithCache } from "../offline/offlineResource";

const TABS = [
  { value: "active", label: "Not completed" },
  { value: "completed", label: "Completed" },
];

function formatVisitTime(iso) {
  if (!iso) return null;
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, { day: "2-digit", month: "short", year: "numeric" }) +
    " · " + d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
}

export default function Patients() {
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("active");
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [fromCache, setFromCache] = useState(false);

  const search = useCallback(async (q, s) => {
    setLoading(true);
    const { data, fromCache } = await getWithCache(`patients:${s}:${q}`, "/patients", { q, status: s });
    setPatients(data);
    setFromCache(fromCache);
    setLoading(false);
  }, []);

  useEffect(() => {
    search(query, status);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  function handleSearch(e) {
    e.preventDefault();
    search(query, status);
  }

  return (
    <div className="space-y-5">
      <PageHeader
        icon={Users}
        title="Patients"
        subtitle="Master patient index — search by name, patient ID, phone, or national ID"
        action={<Button onClick={() => navigate("/reception")} icon={UserPlus}>Register new patient</Button>}
      />

      <div className="flex gap-2">
        {TABS.map((t) => (
          <button
            key={t.value}
            onClick={() => setStatus(t.value)}
            className={`px-3.5 py-1.5 rounded-full text-sm font-medium transition-colors ${
              status === t.value ? "bg-teal-500 text-white" : "bg-surface border border-line text-ink/60"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <form onSubmit={handleSearch} className="flex gap-2">
        <div className="relative flex-1">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search patients…"
            aria-label="Search patients"
            className="pl-9"
          />
        </div>
        <Button type="submit" variant="secondary">Search</Button>
      </form>

      {fromCache && (
        <p className="text-xs text-clay bg-clay/10 border border-clay/20 rounded-lg px-3 py-2">
          Showing cached results from your last connection — reconnect for the latest list.
        </p>
      )}

      {loading ? (
        <LoadingRow />
      ) : patients.length === 0 ? (
        <EmptyState
          title={status === "active" ? "No ongoing patients" : "No completed visits yet"}
          hint={status === "active"
            ? "Everyone currently registered has been discharged, admitted, referred out, or hasn't been registered yet."
            : "Once a patient's visit is discharged, admitted, referred out, or documented as a death, it'll show up here."}
        />
      ) : (
        <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-sm">
            <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
              <tr>
                <th className="text-left px-4 py-3">Patient</th>
                <th className="text-left px-4 py-3">Patient ID</th>
                <th className="text-left px-4 py-3">Sex / Age</th>
                <th className="text-left px-4 py-3">District</th>
                <th className="text-left px-4 py-3">Category</th>
                <th className="text-left px-4 py-3">Last visit</th>
              </tr>
            </thead>
            <tbody>
              {patients.map((p) => (
                <tr
                  key={p.id}
                  className="border-t border-line hover:bg-surface-alt/60 cursor-pointer"
                  onClick={() => navigate(`/patients/${p.patient_uid}`)}
                  tabIndex={0}
                  role="button"
                  onKeyDown={(e) => e.key === "Enter" && navigate(`/patients/${p.patient_uid}`)}
                >
                  <td className="px-4 py-3 font-medium">
                    {p.full_name}
                    {p.is_deceased && <Badge tone="critical" className="ml-2">Deceased</Badge>}
                  </td>
                  <td className="px-4 py-3 mrn-mono text-ink/70">{p.patient_uid}</td>
                  <td className="px-4 py-3 text-ink/70">
                    {p.sex || "—"} / {calcAge(p.date_of_birth, p.estimated_age) ?? "—"}
                  </td>
                  <td className="px-4 py-3 text-ink/70">{p.district || "—"}</td>
                  <td className="px-4 py-3">
                    <Badge>{p.patient_category}</Badge>
                  </td>
                  <td className="px-4 py-3 text-ink/70 whitespace-nowrap">
                    {p.latest_encounter_at ? (
                      <>
                        <span>{formatVisitTime(p.latest_encounter_at)}</span>
                        {p.latest_mrn && <span className="mrn-mono text-xs text-ink/45 ml-1.5">({p.latest_mrn})</span>}
                        {p.has_active_encounter && <Badge tone="success" className="ml-2">ongoing</Badge>}
                      </>
                    ) : (
                      <span className="text-ink/35">No visit yet</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        </Card>
      )}
    </div>
  );
}
