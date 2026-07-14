import { useEffect, useState } from "react";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import {
  Users, Activity, BedDouble, ClipboardPlus, FlaskConical, AlertTriangle,
  Wallet, PackageX, Sparkles,
} from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow } from "../components/ui";
import { useAuth } from "../context/AuthContext";

const PIE_COLORS = ["#0F4C4A", "#3F8C84", "#D98E2F", "#C8443C", "#3A7D5C"];

export default function Dashboard() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    api
      .get("/dashboard/summary")
      .then((res) => setData(res.data))
      .catch(() => setError(true));
  }, []);

  if (error) {
    return (
      <Card>
        <p className="text-sm text-ink/60">
          Dashboard data isn't available right now — you may be offline. It will load once you're reconnected.
        </p>
      </Card>
    );
  }
  if (!data) return <LoadingRow label="Loading dashboard…" />;

  const deptData = Object.entries(data.department_queue_counts).map(([name, value]) => ({ name, value }));
  const priorityData = Object.entries(data.priority_breakdown).map(([name, value]) => ({ name, value }));

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2.5">
        <div className="h-10 w-10 rounded-xl bg-teal-500/10 flex items-center justify-center text-teal-500 shrink-0">
          <Sparkles size={19} strokeWidth={2} />
        </div>
        <div>
          <p className="font-display text-2xl">Good day, {user?.full_name?.split(" ")[0]}</p>
          <p className="text-sm text-ink/50">Hospital overview as of right now</p>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Total patients registered" value={data.total_patients} icon={Users} tint="teal" />
        <StatCard label="Active encounters" value={data.active_encounters} icon={Activity} tint="teal" />
        <StatCard label="Admitted patients" value={data.admitted_patients} icon={BedDouble} tint="teal" />
        <StatCard label="Registered today" value={data.today_registrations} icon={ClipboardPlus} tint="teal" />
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard
          label="Pending lab orders"
          value={data.pending_lab_orders}
          icon={FlaskConical}
          tone={data.pending_lab_orders > 10 ? "warning" : "neutral"}
        />
        <StatCard
          label="Unacknowledged critical results"
          value={data.critical_results_unacknowledged}
          icon={AlertTriangle}
          tone={data.critical_results_unacknowledged > 0 ? "critical" : "success"}
        />
        <StatCard
          label="Outstanding billing (MWK)"
          value={data.outstanding_billing_total.toLocaleString()}
          icon={Wallet}
        />
        <StatCard
          label="Low stock drugs"
          value={data.low_stock_drug_count}
          icon={PackageX}
          tone={data.low_stock_drug_count > 0 ? "warning" : "neutral"}
        />
      </div>

      <div className="grid md:grid-cols-2 gap-4">
        <Card>
          <p className="font-display text-lg mb-3">Visits, last 7 days</p>
          <ResponsiveContainer width="100%" height={240}>
            <BarChart data={data.visits_last_7_days}>
              <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-line))" />
              <XAxis dataKey="date" tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} tickFormatter={(d) => d.slice(5)} />
              <YAxis tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} allowDecimals={false} />
              <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
              <Bar dataKey="count" fill="#0F4C4A" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </Card>

        <Card>
          <p className="font-display text-lg mb-3">Active encounters by department</p>
          {deptData.length === 0 ? (
            <p className="text-sm text-ink/40 py-10 text-center">No active encounters right now.</p>
          ) : (
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={deptData} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" stroke="rgb(var(--color-line))" />
                <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
                <YAxis type="category" dataKey="name" width={100} tick={{ fontSize: 11, fill: "rgb(var(--color-ink) / 0.6)" }} />
                <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
                <Bar dataKey="value" fill="#3F8C84" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </Card>
      </div>

      {priorityData.length > 0 && (
        <Card className="md:w-1/2">
          <p className="font-display text-lg mb-3">Active encounters by priority</p>
          <ResponsiveContainer width="100%" height={200}>
            <PieChart>
              <Pie data={priorityData} dataKey="value" nameKey="name" outerRadius={70} label>
                {priorityData.map((entry, i) => (
                  <Cell key={entry.name} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                ))}
              </Pie>
              <Tooltip contentStyle={{ background: "rgb(var(--color-surface))", border: "1px solid rgb(var(--color-line))", borderRadius: 8, fontSize: 13 }} />
            </PieChart>
          </ResponsiveContainer>
        </Card>
      )}
    </div>
  );
}

function StatCard({ label, value, tone, icon: Icon }) {
  const iconTones = {
    neutral: "bg-teal-500/10 text-teal-500",
    teal: "bg-teal-500/10 text-teal-500",
    success: "bg-moss/10 text-moss",
    warning: "bg-clay/10 text-clay",
    critical: "bg-alert/10 text-alert",
  };
  return (
    <Card className="p-4">
      <div className="flex items-start justify-between mb-2">
        <p className="text-xs text-ink/50 leading-snug pr-2">{label}</p>
        {Icon && (
          <div className={`h-8 w-8 rounded-lg flex items-center justify-center shrink-0 ${iconTones[tone || "neutral"]}`}>
            <Icon size={16} strokeWidth={2} />
          </div>
        )}
      </div>
      <p className="font-display text-2xl">{value}</p>
      {tone && tone !== "neutral" && tone !== "teal" && (
        <div className="mt-2">
          <Badge tone={tone}>{tone === "critical" ? "Needs attention" : tone === "warning" ? "Monitor" : "Normal"}</Badge>
        </div>
      )}
    </Card>
  );
}
