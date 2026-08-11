import { useState, useEffect, useCallback, useMemo } from "react";
import { useNavigate } from "react-router-dom";
import { 
  Users, 
  Search, 
  UserPlus, 
  ArrowUpDown, 
  ArrowUp, 
  ArrowDown, 
  SlidersHorizontal,
  Calendar,
  User,
  Hash
} from "lucide-react";
import api from "../services/api";
import { Card, Input, Button, Badge, EmptyState, LoadingRow, calcAge } from "../components/ui";
import PageHeader from "../components/PageHeader";
import { getWithCache } from "../offline/offlineResource";
import { useAuth } from "../context/AuthContext";

const TABS = [
  { value: "active", label: "Not completed" },
  { value: "completed", label: "Completed" },
];

const SORT_OPTIONS = [
  { value: "date", label: "Last Visit", icon: Calendar },
  { value: "name", label: "Name", icon: User },
  { value: "id", label: "Patient ID", icon: Hash },
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

  // Sorting state
  const { hasRole } = useAuth();
  const [sortField, setSortField] = useState("date"); // 'name' | 'date' | 'id'
  const [sortOrder, setSortOrder] = useState("desc"); // 'asc' | 'desc'

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

  const handleSortToggle = (field) => {
    if (sortField === field) {
      setSortOrder((prev) => (prev === "asc" ? "desc" : "asc"));
    } else {
      setSortField(field);
      setSortOrder("asc");
    }
  };

  // Process sorting on retrieved patients
  const sortedPatients = useMemo(() => {
    return [...patients].sort((a, b) => {
      let valA, valB;

      if (sortField === "name") {
        valA = a.full_name?.toLowerCase() || "";
        valB = b.full_name?.toLowerCase() || "";
      } else if (sortField === "date") {
        valA = a.latest_encounter_at ? new Date(a.latest_encounter_at).getTime() : 0;
        valB = b.latest_encounter_at ? new Date(b.latest_encounter_at).getTime() : 0;
      } else if (sortField === "id") {
        valA = a.patient_uid?.toLowerCase() || "";
        valB = b.patient_uid?.toLowerCase() || "";
      }

      if (valA < valB) return sortOrder === "asc" ? -1 : 1;
      if (valA > valB) return sortOrder === "asc" ? 1 : -1;
      return 0;
    });
  }, [patients, sortField, sortOrder]);

  return (
    <div className="space-y-6">
      <PageHeader
        icon={Users}
        title="Patients"
        subtitle="Master patient index — search by name, patient ID, phone, or national ID"
        action={hasRole('reception', 'admin') ? <Button onClick={() => navigate("/reception")} icon={UserPlus}>Register new patient</Button> : null}
      />

      {/* Tabs */}
      <div className="flex gap-2 border-b border-line pb-3">
        {TABS.map((t) => (
          <button
            key={t.value}
            onClick={() => setStatus(t.value)}
            className={`px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-150 ${
              status === t.value 
                ? "bg-teal-500 text-white shadow-sm shadow-teal-500/20" 
                : "bg-surface hover:bg-surface-alt border border-line text-ink/60"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Search & Sort Command Bar */}
      <div className="p-3 bg-surface border border-line rounded-2xl shadow-sm space-y-3 lg:space-y-0 lg:flex lg:items-center lg:gap-3">
        {/* Search Input */}
        <form onSubmit={handleSearch} className="flex gap-2 flex-1">
          <div className="relative flex-1">
            <Search size={18} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search patients by name, ID, phone…"
              aria-label="Search patients"
              className="pl-10 h-10 border-line/60 rounded-xl focus:border-teal-500 transition-colors"
            />
          </div>
          <Button type="submit" variant="secondary" className="h-10 px-5 rounded-xl font-medium">Search</Button>
        </form>

        {/* Sort Controls Toolbar */}
        <div className="flex items-center gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-line/60">
          <div className="flex items-center text-xs font-semibold uppercase tracking-wider text-ink/40 pl-1 mr-1 hidden sm:flex">
            <SlidersHorizontal size={14} className="mr-1.5" />
            Sort
          </div>

          {/* Sort Field Segmented Buttons */}
          <div className="flex bg-surface-alt p-1 rounded-xl border border-line/60 gap-1 flex-1 sm:flex-none">
            {SORT_OPTIONS.map((opt) => {
              const Icon = opt.icon;
              const isActive = sortField === opt.value;
              return (
                <button
                  key={opt.value}
                  onClick={() => handleSortToggle(opt.value)}
                  className={`flex-1 sm:flex-none flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                    isActive
                      ? "bg-surface text-teal-600 shadow-sm font-semibold"
                      : "text-ink/60 hover:text-ink hover:bg-surface/50"
                  }`}
                >
                  <Icon size={13} className={isActive ? "text-teal-500" : "opacity-60"} />
                  <span>{opt.label}</span>
                </button>
              );
            })}
          </div>

          {/* Sort Direction Toggle Button */}
          <button
            onClick={() => setSortOrder((prev) => (prev === "asc" ? "desc" : "asc"))}
            className="flex items-center justify-center p-2.5 h-10 w-10 bg-surface-alt hover:bg-surface border border-line/60 rounded-xl text-ink/70 transition-all active:scale-95"
            title={`Order: ${sortOrder === "asc" ? "Ascending (Click for Descending)" : "Descending (Click for Ascending)"}`}
          >
            {sortOrder === "asc" ? (
              <ArrowUp size={16} className="text-teal-600" />
            ) : (
              <ArrowDown size={16} className="text-teal-600" />
            )}
          </button>
        </div>
      </div>

      {fromCache && (
        <p className="text-xs text-clay bg-clay/10 border border-clay/20 rounded-xl px-4 py-2.5 flex items-center gap-2">
          <span className="h-2 w-2 rounded-full bg-clay animate-pulse" />
          Showing cached results from your last connection — reconnect for the latest list.
        </p>
      )}

      {loading ? (
        <LoadingRow />
      ) : sortedPatients.length === 0 ? (
        <EmptyState
          title={status === "active" ? "No ongoing patients" : "No completed visits yet"}
          hint={status === "active"
            ? "Everyone currently registered has been discharged, admitted, referred out, or hasn't been registered yet."
            : "Once a patient's visit is discharged, admitted, referred out, or documented as a death, it'll show up here."}
        />
      ) : (
        <Card className="p-0 overflow-hidden border border-line rounded-2xl shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm border-collapse">
              <thead className="bg-surface-alt/70 text-ink/60 text-[11px] font-semibold uppercase tracking-wider border-b border-line">
                <tr>
                  <th
                    className={`text-left px-4 py-3.5 cursor-pointer select-none transition-colors hover:bg-surface-alt ${
                      sortField === "name" ? "text-teal-600 font-bold bg-teal-500/5" : ""
                    }`}
                    onClick={() => handleSortToggle("name")}
                  >
                    <div className="flex items-center gap-1">
                      Patient
                      {sortField === "name" ? (
                        sortOrder === "asc" ? <ArrowUp size={13} /> : <ArrowDown size={13} />
                      ) : (
                        <ArrowUpDown size={13} className="opacity-30" />
                      )}
                    </div>
                  </th>
                  <th
                    className={`text-left px-4 py-3.5 cursor-pointer select-none transition-colors hover:bg-surface-alt ${
                      sortField === "id" ? "text-teal-600 font-bold bg-teal-500/5" : ""
                    }`}
                    onClick={() => handleSortToggle("id")}
                  >
                    <div className="flex items-center gap-1">
                      Patient ID
                      {sortField === "id" ? (
                        sortOrder === "asc" ? <ArrowUp size={13} /> : <ArrowDown size={13} />
                      ) : (
                        <ArrowUpDown size={13} className="opacity-30" />
                      )}
                    </div>
                  </th>
                  <th className="text-left px-4 py-3.5">Sex / Age</th>
                  <th className="text-left px-4 py-3.5">District</th>
                  <th className="text-left px-4 py-3.5">Category</th>
                  <th
                    className={`text-left px-4 py-3.5 cursor-pointer select-none transition-colors hover:bg-surface-alt ${
                      sortField === "date" ? "text-teal-600 font-bold bg-teal-500/5" : ""
                    }`}
                    onClick={() => handleSortToggle("date")}
                  >
                    <div className="flex items-center gap-1">
                      Last visit
                      {sortField === "date" ? (
                        sortOrder === "asc" ? <ArrowUp size={13} /> : <ArrowDown size={13} />
                      ) : (
                        <ArrowUpDown size={13} className="opacity-30" />
                      )}
                    </div>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line/60">
                {sortedPatients.map((p) => (
                  <tr
                    key={p.id}
                    className="hover:bg-teal-500/[0.03] transition-colors cursor-pointer group"
                    onClick={() => navigate(`/patients/${p.patient_uid}`)}
                    tabIndex={0}
                    role="button"
                    onKeyDown={(e) => e.key === "Enter" && navigate(`/patients/${p.patient_uid}`)}
                  >
                    <td className="px-4 py-3.5 font-medium group-hover:text-teal-600 transition-colors">
                      {p.full_name}
                      {p.is_deceased && <Badge tone="critical" className="ml-2">Deceased</Badge>}
                    </td>
                    <td className="px-4 py-3.5 mrn-mono text-ink/70 font-mono text-xs">{p.patient_uid}</td>
                    <td className="px-4 py-3.5 text-ink/70">
                      {p.sex || "—"} / {calcAge(p.date_of_birth, p.estimated_age) ?? "—"}
                    </td>
                    <td className="px-4 py-3.5 text-ink/70">{p.district || "—"}</td>
                    <td className="px-4 py-3.5">
                      <Badge>{p.patient_category}</Badge>
                    </td>
                    <td className="px-4 py-3.5 text-ink/70 whitespace-nowrap">
                      {p.latest_encounter_at ? (
                        <div className="flex items-center gap-1.5">
                          <span>{formatVisitTime(p.latest_encounter_at)}</span>
                          {p.latest_mrn && <span className="mrn-mono text-xs text-ink/45">({p.latest_mrn})</span>}
                          {p.has_active_encounter && <Badge tone="success">ongoing</Badge>}
                        </div>
                      ) : (
                        <span className="text-ink/35 italic">No visit yet</span>
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