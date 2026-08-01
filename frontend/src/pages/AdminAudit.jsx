import { useEffect, useState } from "react";
import { ScrollText, ShieldAlert } from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function AdminAudit() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [securityAlerts, setSecurityAlerts] = useState([]);

  useEffect(() => {
    api.get("/audit").then((res) => setLogs(res.data)).catch(() => setLogs([])).finally(() => setLoading(false));
    api.get("/auth/security-alerts").then((res) => setSecurityAlerts(res.data.alerts)).catch(() => setSecurityAlerts([]));
  }, []);

  return (
    <div className="space-y-5">
      <PageHeader icon={ScrollText} title="Audit trail" subtitle="Every clinically or administratively significant action, for accountability and governance." />

      {securityAlerts.length > 0 && (
        <Card className="border-alert/30 bg-alert/5">
          <p className="font-semibold text-alert flex items-center gap-2 mb-2">
            <ShieldAlert size={16} /> Security alerts — repeated failed login attempts (last 24 hours)
          </p>
          <ul className="text-sm space-y-1.5">
            {securityAlerts.map((a) => (
              <li key={a.username} className="flex items-center justify-between flex-wrap gap-2">
                <span>
                  <span className="font-medium">{a.username}</span> — {a.failed_count} failed attempts
                  {a.ip_addresses.length > 0 && <span className="text-ink/50"> from {a.ip_addresses.join(", ")}</span>}
                </span>
                <span className="text-xs text-ink/45">latest: {new Date(a.latest_attempt).toLocaleString()}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {loading ? <LoadingRow /> : logs.length === 0 ? (
        <EmptyState title="No audit entries yet" />
      ) : (
        <Card className="p-0 overflow-hidden">
          <div className="overflow-x-auto">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-surface-alt text-accent text-xs uppercase tracking-wide">
              <tr>
                <th className="text-left px-4 py-3">Time</th>
                <th className="text-left px-4 py-3">User</th>
                <th className="text-left px-4 py-3">Action</th>
                <th className="text-left px-4 py-3">Entity</th>
                <th className="text-left px-4 py-3">Details</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id} className="border-t border-line">
                  <td className="px-4 py-3 text-ink/60 text-xs mrn-mono">{new Date(log.timestamp).toLocaleString()}</td>
                  <td className="px-4 py-3">{log.username}</td>
                  <td className="px-4 py-3"><Badge tone="muted">{log.action}</Badge></td>
                  <td className="px-4 py-3 text-ink/60">{log.entity_type} #{log.entity_id}</td>
                  <td className="px-4 py-3 text-ink/50 text-xs">{log.details}</td>
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
