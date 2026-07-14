import { useNavigate } from "react-router-dom";
import { Card, Badge, priorityTone, calcAge, EmptyState } from "./ui";

export default function DepartmentQueue({ title, hint, encounters, emptyHint }) {
  const navigate = useNavigate();
  if (!encounters.length) {
    return <EmptyState title={`No patients waiting in ${title}`} hint={emptyHint} />;
  }
  return (
    <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
      <table className="w-full min-w-[640px] text-sm">
        <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
          <tr>
            <th className="text-left px-4 py-3">Patient</th>
            <th className="text-left px-4 py-3">MRN</th>
            <th className="text-left px-4 py-3">Age/Sex</th>
            <th className="text-left px-4 py-3">Complaint</th>
            <th className="text-left px-4 py-3">Priority</th>
            <th className="text-left px-4 py-3">Stage</th>
          </tr>
        </thead>
        <tbody>
          {encounters.map((e) => (
            <tr
              key={e.id}
              className="border-t border-line hover:bg-surface-alt/60 cursor-pointer"
              onClick={() => navigate(`/encounters/${e.id}`)}
            >
              <td className="px-4 py-3 font-medium">{e.patient?.full_name}</td>
              <td className="px-4 py-3 mrn-mono text-ink/70">{e.patient?.mrn}</td>
              <td className="px-4 py-3 text-ink/70">
                {calcAge(e.patient?.date_of_birth, e.patient?.estimated_age) ?? "—"} / {e.patient?.sex || "—"}
              </td>
              <td className="px-4 py-3 text-ink/70">{e.chief_complaint || "—"}</td>
              <td className="px-4 py-3"><Badge tone={priorityTone(e.priority)}>{e.priority}</Badge></td>
              <td className="px-4 py-3"><Badge tone="muted">{e.stage.replace("_", " ")}</Badge></td>
            </tr>
          ))}
        </tbody>
      </table>
          </div>
    </Card>
  );
}
