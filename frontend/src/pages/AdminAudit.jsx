import { useEffect, useState } from "react";
import { ScrollText } from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow, EmptyState } from "../components/ui";
import PageHeader from "../components/PageHeader";

export default function AdminAudit() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get("/audit").then((res) => setLogs(res.data)).catch(() => setLogs([])).finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-5">
      <PageHeader icon={ScrollText} title="Audit trail" subtitle="Every clinically or administratively significant action, for accountability and governance." />

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
