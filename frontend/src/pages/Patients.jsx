import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { Users, Search, UserPlus, CheckCircle2, CircleDashed } from "lucide-react";
import api from "../services/api";
import { Card, Input, Button, Badge, EmptyState, LoadingRow, calcAge } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { getWithCache } from "../offline/offlineResource";

export default function Patients() {
  const navigate = useNavigate();
  const [query, setQuery] = useState("");
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [fromCache, setFromCache] = useState(false);
  const [statusFilter, setStatusFilter] = useState("not_completed");

  const search = useCallback(async (q, selectedStatus = statusFilter) => {
    setLoading(true);
    const { data, fromCache } = await getWithCache(`patients:${selectedStatus}:${q}`, "/patients", { q, status: selectedStatus });
    setPatients(data);
    setFromCache(fromCache);
    setLoading(false);
  }, []);

  useEffect(() => {
    search("");
  }, [search]);

  function handleSearch(e) {
    e.preventDefault();
    search(query, statusFilter);
  }

  function toggleStatus(nextStatus) {
    setStatusFilter(nextStatus);
    search(query, nextStatus);
  }

  return (
    <div className="space-y-5">
      <PageHeader
        icon={Users}
        title="Patients"
        subtitle="Master patient index — search by name, MRN, phone, or national ID"
        action={<Button onClick={() => navigate("/reception")} icon={UserPlus}>Register new patient</Button>}
      />

      <form onSubmit={handleSearch} className="flex flex-col gap-2 md:flex-row">
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
        <div className="flex gap-2">
          <Button type="button" variant={statusFilter === "not_completed" ? "primary" : "secondary"} onClick={() => toggleStatus("not_completed")} icon={CircleDashed}>Not completed</Button>
          <Button type="button" variant={statusFilter === "completed" ? "primary" : "secondary"} onClick={() => toggleStatus("completed")} icon={CheckCircle2}>Completed</Button>
          <Button type="submit" variant="secondary">Search</Button>
        </div>
      </form>

      {fromCache && (
        <p className="text-xs text-clay bg-clay/10 border border-clay/20 rounded-lg px-3 py-2">
          Showing cached results from your last connection — reconnect for the latest list.
        </p>
      )}

      {loading ? (
        <LoadingRow />
      ) : patients.length === 0 ? (
        <EmptyState title="No patients found" hint="Try a different search term, or register a new patient." />
      ) : (
        <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
              <tr>
                <th className="text-left px-4 py-3">Patient</th>
                <th className="text-left px-4 py-3">MRN</th>
                <th className="text-left px-4 py-3">Sex / Age</th>
                <th className="text-left px-4 py-3">District</th>
                <th className="text-left px-4 py-3">Category</th>
                <th className="text-left px-4 py-3">Referred doctor</th>
              </tr>
            </thead>
            <tbody>
              {patients.map((p) => (
                <tr
                  key={p.id}
                  className="border-t border-line hover:bg-surface-alt/60 cursor-pointer"
                  onClick={() => navigate(`/patients/${p.id}`)}
                  tabIndex={0}
                  role="button"
                  onKeyDown={(e) => e.key === "Enter" && navigate(`/patients/${p.id}`)}
                >
                  <td className="px-4 py-3 font-medium">
                    {p.full_name}
                    {p.is_deceased && <Badge tone="critical" className="ml-2">Deceased</Badge>}
                  </td>
                  <td className="px-4 py-3 mrn-mono text-ink/70">{p.mrn}</td>
                  <td className="px-4 py-3 text-ink/70">
                    {p.sex || "—"} / {calcAge(p.date_of_birth, p.estimated_age) ?? "—"}
                  </td>
                  <td className="px-4 py-3 text-ink/70">{p.district || "—"}</td>
                  <td className="px-4 py-3">
                    <Badge>{p.patient_category}</Badge>
                  </td>
                  <td className="px-4 py-3 text-ink/70">
                    {p.referred_doctor ? `${p.referred_doctor}${p.referred_doctor_department ? ` · ${p.referred_doctor_department}` : ""}` : "—"}
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
