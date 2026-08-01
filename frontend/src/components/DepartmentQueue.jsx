import { useNavigate } from "react-router-dom";
import { Siren } from "lucide-react";
import { Card, Badge, priorityTone, calcAge, EmptyState } from "./ui";

export default function DepartmentQueue({ title, hint, encounters, emptyHint }) {
  const navigate = useNavigate();
  if (!encounters.length) {
    return <EmptyState title={`No patients waiting in ${title}`} hint={emptyHint} />;
  }
  return (
    <Card className="p-0 overflow-hidden">
      <div className="overflow-x-auto">
      <table className="w-full min-w-[760px] text-sm">
        <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
          <tr>
            <th className="text-left px-4 py-3">Patient</th>
            <th className="text-left px-4 py-3">Visit MRN</th>
            <th className="text-left px-4 py-3">Age/Sex</th>
            <th className="text-left px-4 py-3">Complaint</th>
            <th className="text-left px-4 py-3">Referred by</th>
            <th className="text-left px-4 py-3">Priority</th>
            <th className="text-left px-4 py-3">Stage</th>
          </tr>
        </thead>
        <tbody>
          {encounters.map((e) => (
            <tr
              key={e.id}
              className={`border-t border-line hover:bg-surface-alt/60 cursor-pointer ${e.is_emergency ? "bg-alert/5" : ""}`}
              onClick={() => navigate(`/encounters/${e.id}`)}
            >
              <td className="px-4 py-3 font-medium">
                <span className="flex items-center gap-1.5">
                  {e.is_emergency && <Siren size={13} className="text-alert shrink-0" />}
                  {e.patient?.full_name}
                </span>
              </td>
              <td className="px-4 py-3 mrn-mono text-ink/70">{e.mrn}</td>
              <td className="px-4 py-3 text-ink/70">
                {calcAge(e.patient?.date_of_birth, e.patient?.estimated_age) ?? "—"} / {e.patient?.sex || "—"}
              </td>
              <td className="px-4 py-3 text-ink/70">{e.chief_complaint || "—"}</td>
              <td className="px-4 py-3 text-ink/70 max-w-[220px]">
                {e.referral ? (
                  <span title={e.referral.message || ""}>
                    <span className="font-medium">{e.referral.referred_by_name || "Unknown"}</span>
                    {e.referral.message && <span className="text-ink/45"> — {e.referral.message}</span>}
                  </span>
                ) : (
                  <span className="text-ink/30">—</span>
                )}
              </td>
              <td className="px-4 py-3">
                {e.is_emergency ? <Badge tone="critical" icon={Siren}>emergency</Badge> : <Badge tone={priorityTone(e.priority)}>{e.priority}</Badge>}
              </td>
              <td className="px-4 py-3"><Badge tone="muted">{e.stage.replace("_", " ")}</Badge></td>
            </tr>
          ))}
        </tbody>
      </table>
      </div>
    </Card>
  );
}
