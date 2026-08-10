import { useEffect, useState } from "react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  LineChart,
  Line,
} from "recharts";
import {
  Users,
  Activity,
  BedDouble,
  ClipboardPlus,
  FlaskConical,
  AlertTriangle,
  Wallet,
  PackageX,
  Sparkles,
  MessageSquareText,
  Calendar,
  ArrowUpRight,
} from "lucide-react";
import api from "../services/api";
import { Card, Badge, LoadingRow } from "../components/ui";
import { useAuth } from "../context/AuthContext";
import { Link } from "react-router-dom";

const COLOR_PALETTE = [
  "#0F4C4A",
  "#3F8C84",
  "#D98E2F",
  "#C8443C",
  "#3A7D5C",
];

export default function Dashboard() {
  const { user } = useAuth();

  const [data, setData] = useState(null);
  const [error, setError] = useState(false);
  const [messages, setMessages] = useState([]);

  useEffect(() => {
    api
      .get("/dashboard/summary")
      .then((res) => setData(res.data))
      .catch(() => setError(true));

    api
      .get("/referrals/inbox")
      .then((res) => setMessages(res.data.messages.slice(0, 5)))
      .catch(() => setMessages([]));
  }, []);

  if (error) {
    return (
      <Card className="w-full mx-auto mt-12 p-6 text-center border-red-500/20 bg-red-50/50 dark:bg-red-950/10">
        <AlertTriangle
          className="mx-auto text-alert mb-2"
          size={28}
        />

        <p className="text-sm font-medium text-ink/70">
          Dashboard data isn't available right now — you may be
          offline. It will load once you're reconnected.
        </p>
      </Card>
    );
  }

  if (!data) {
    return <LoadingRow label="Loading dashboard…" />;
  }

  const deptData = Object.entries(
    data.department_queue_counts ?? {}
  ).map(([name, value]) => ({
    name,
    value,
  }));

  const priorityData = Object.entries(
    data.priority_breakdown ?? {}
  ).map(([name, value]) => ({
    name,
    value,
  }));

  const has = (key) =>
    Object.prototype.hasOwnProperty.call(data, key);

  return (
    <div className="space-y-8 pb-10">

      {/* =====================================================
          TOP NAVIGATION & BRANDING BAR
          ===================================================== */}
      <header className="sticky top-0 z-20 backdrop-blur-md bg-surface/80 border-b border-line -mx-6 -mt-6 px-6 py-3.5 mb-6 flex items-center justify-between transition-all">

        <div className="flex items-center gap-3">
          <img
            src={`${import.meta.env.BASE_URL}emr.png`}
            alt="EMR Logo"
            className="h-9 w-auto object-contain rounded-lg"
          />
        </div>

        <div className="flex items-center gap-3">

          <div className="hidden md:flex items-center gap-2 text-xs font-medium text-ink/60 bg-line/30 px-3 py-1.5 rounded-full">
            <Calendar size={13} className="text-teal-500" />

            <span>
              {new Date().toLocaleDateString(undefined, {
                weekday: "short",
                month: "short",
                day: "numeric",
              })}
            </span>
          </div>

          <div className="flex items-center gap-2.5 pl-2 border-l border-line">

            <div className="h-8 w-8 rounded-full bg-teal-500/10 text-teal-600 font-bold flex items-center justify-center text-xs ring-2 ring-teal-500/20">
              {user?.full_name?.charAt(0) || "U"}
            </div>

            <span className="text-xs font-medium text-ink/80 hidden sm:inline">
              {user?.full_name}
            </span>

          </div>
        </div>
      </header>


      {/* =====================================================
          HERO WELCOME BANNER
          ===================================================== */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-teal-900 via-teal-800 to-emerald-900 p-6 text-white shadow-xl shadow-teal-950/5">

        <div className="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
          <Sparkles size={240} />
        </div>

        <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">

          <div>

            <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-white/10 backdrop-blur-md text-teal-200 text-xs font-medium mb-2 border border-white/10">
              <Sparkles size={13} />
              Real-time System Overview
            </div>

            <h1 className="font-display text-2xl sm:text-3xl font-semibold tracking-tight">
              Good day, {user?.full_name?.split(" ")[0]}
            </h1>

            <p className="text-teal-100/70 text-sm mt-1">
              Here is what is happening across the facility today.
            </p>

          </div>
        </div>
      </div>


      {/* =====================================================
          PRIMARY KEY METRICS
          ===================================================== */}
      {(has("total_patients") ||
        has("active_encounters") ||
        has("admitted_patients") ||
        has("today_registrations")) && (

        <section className="space-y-3">

          <h2 className="text-xs font-semibold tracking-wider text-ink/50 uppercase px-1">
            Patient Operations
          </h2>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">

            {has("total_patients") && (
              <StatCard
                label="Total Patients"
                value={data.total_patients}
                icon={Users}
                tint="teal"
                trend="+2.4%"
              />
            )}

            {has("active_encounters") && (
              <StatCard
                label="Active Encounters"
                value={data.active_encounters}
                icon={Activity}
                tint="teal"
              />
            )}

            {has("admitted_patients") && (
              <StatCard
                label="Admitted Patients"
                value={data.admitted_patients}
                icon={BedDouble}
                tint="teal"
              />
            )}

            {has("today_registrations") && (
              <StatCard
                label="Registered Today"
                value={data.today_registrations}
                icon={ClipboardPlus}
                tint="teal"
              />
            )}

          </div>
        </section>
      )}


      {/* =====================================================
          ALERTS & CRITICAL METRICS
          ===================================================== */}
      {(has("pending_lab_orders") ||
        has("critical_results_unacknowledged") ||
        has("outstanding_billing_total") ||
        has("low_stock_drug_count")) && (

        <section className="space-y-3">

          <h2 className="text-xs font-semibold tracking-wider text-ink/50 uppercase px-1">
            Operational Alerts
          </h2>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">

            {has("pending_lab_orders") && (
              <StatCard
                label="Pending Lab Orders"
                value={data.pending_lab_orders}
                icon={FlaskConical}
                tone={
                  data.pending_lab_orders > 10
                    ? "warning"
                    : "neutral"
                }
              />
            )}

            {has("critical_results_unacknowledged") && (
              <StatCard
                label="Critical Lab Results"
                value={data.critical_results_unacknowledged}
                icon={AlertTriangle}
                tone={
                  data.critical_results_unacknowledged > 0
                    ? "critical"
                    : "success"
                }
              />
            )}

            {has("outstanding_billing_total") && (
              <StatCard
                label="Outstanding Billing (MWK)"
                value={data.outstanding_billing_total.toLocaleString()}
                icon={Wallet}
              />
            )}

            {has("low_stock_drug_count") && (
              <StatCard
                label="Low Stock Drugs"
                value={data.low_stock_drug_count}
                icon={PackageX}
                tone={
                  data.low_stock_drug_count > 0
                    ? "warning"
                    : "neutral"
                }
              />
            )}

          </div>
        </section>
      )}


      {/* =====================================================
          RECENT MESSAGES
          ===================================================== */}
      {messages.length > 0 && (
        <Card className="border border-line/60 shadow-sm rounded-2xl overflow-hidden">

          <div className="p-4 border-b border-line flex items-center justify-between bg-surface/50">

            <div className="flex items-center gap-2">

              <div className="p-1.5 rounded-lg bg-teal-500/10 text-teal-600">
                <MessageSquareText size={16} />
              </div>

              <h3 className="font-display font-medium text-base">
                Recent Referrals & Messages
              </h3>

            </div>

            <Link
              to="/messages"
              className="text-xs font-semibold text-teal-600 hover:text-teal-700 dark:text-teal-400 flex items-center gap-1 transition-colors"
            >
              <span>View all</span>
              <ArrowUpRight size={14} />
            </Link>

          </div>

          <div className="divide-y divide-line/60">

            {messages.map((m) => (
              <div
                key={m.id}
                className="p-3.5 hover:bg-line/20 transition-colors flex items-center gap-3"
              >

                <div className="w-2 flex justify-center">
                  {!m.is_read && (
                    <span className="h-2 w-2 rounded-full bg-teal-500 animate-pulse" />
                  )}
                </div>

                <div className="min-w-0 flex-1 flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-sm">

                  <div className="flex items-center gap-2 truncate">

                    <Badge
                      tone="neutral"
                      className="text-xs font-mono"
                    >
                      {m.from_department}
                    </Badge>

                    <span className="font-medium text-ink truncate">
                      {m.patient?.full_name}
                    </span>

                  </div>

                  {m.reason && (
                    <span className="text-xs text-ink/50 truncate max-w-xs">
                      {m.reason}
                    </span>
                  )}

                </div>
              </div>
            ))}

          </div>
        </Card>
      )}


      {/* =====================================================
          ANALYTICS
          ===================================================== */}
      <div className="grid md:grid-cols-2 gap-6">

        {/* Visit Volume */}
        <Card className="p-5 border border-line/60 rounded-2xl shadow-sm">

          <div className="flex items-center justify-between mb-4">

            <div>

              <h3 className="font-display text-lg font-medium">
                Patient Visit Volume
              </h3>

              <p className="text-xs text-ink/50">
                Daily encounters recorded over the last 7 days
              </p>

            </div>
          </div>

          <ResponsiveContainer width="100%" height={240}>

            <AreaChart
              data={data.visits_last_7_days ?? []}
              margin={{
                top: 10,
                right: 10,
                left: -25,
                bottom: 0,
              }}
            >

              <defs>
                <linearGradient
                  id="visitGradient"
                  x1="0"
                  y1="0"
                  x2="0"
                  y2="1"
                >
                  <stop
                    offset="5%"
                    stopColor="#0F4C4A"
                    stopOpacity={0.3}
                  />

                  <stop
                    offset="95%"
                    stopColor="#0F4C4A"
                    stopOpacity={0}
                  />
                </linearGradient>
              </defs>

              <CartesianGrid
                strokeDasharray="3 3"
                vertical={false}
                stroke="rgb(var(--color-line))"
                opacity={0.5}
              />

              <XAxis
                dataKey="date"
                tickLine={false}
                tick={{
                  fontSize: 11,
                  fill: "rgb(var(--color-ink) / 0.5)",
                }}
                tickFormatter={(d) => d.slice(5)}
              />

              <YAxis
                tickLine={false}
                axisLine={false}
                tick={{
                  fontSize: 11,
                  fill: "rgb(var(--color-ink) / 0.5)",
                }}
                allowDecimals={false}
              />

              <Tooltip
                contentStyle={{
                  background: "rgba(var(--color-surface), 0.95)",
                  backdropFilter: "blur(8px)",
                  border: "1px solid rgb(var(--color-line))",
                  borderRadius: 12,
                  boxShadow:
                    "0 10px 15px -3px rgba(0, 0, 0, 0.1)",
                  fontSize: 12,
                }}
              />

              <Area
                type="monotone"
                dataKey="count"
                stroke="#0F4C4A"
                strokeWidth={3}
                fillOpacity={1}
                fill="url(#visitGradient)"
              />

            </AreaChart>
          </ResponsiveContainer>
        </Card>


        {/* Department Volume */}
        <Card className="p-5 border border-line/60 rounded-2xl shadow-sm">

          <div className="flex items-center justify-between mb-4">

            <div>

              <h3 className="font-display text-lg font-medium">
                Active Queue by Department
              </h3>

              <p className="text-xs text-ink/50">
                Current live distribution across units
              </p>

            </div>
          </div>

          {deptData.length === 0 ? (
            <div className="h-[240px] flex items-center justify-center">
              <p className="text-sm text-ink/40">
                No active department queues right now.
              </p>
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={240}>

              <LineChart
                data={deptData}
                margin={{
                  top: 10,
                  right: 10,
                  left: -25,
                  bottom: 0,
                }}
              >

                <CartesianGrid
                  strokeDasharray="3 3"
                  vertical={false}
                  stroke="rgb(var(--color-line))"
                  opacity={0.5}
                />

                <XAxis
                  dataKey="name"
                  tickLine={false}
                  tick={{
                    fontSize: 11,
                    fill: "rgb(var(--color-ink) / 0.5)",
                  }}
                />

                <YAxis
                  tickLine={false}
                  axisLine={false}
                  tick={{
                    fontSize: 11,
                    fill: "rgb(var(--color-ink) / 0.5)",
                  }}
                  allowDecimals={false}
                />

                <Tooltip
                  contentStyle={{
                    background:
                      "rgba(var(--color-surface), 0.95)",
                    backdropFilter: "blur(8px)",
                    border:
                      "1px solid rgb(var(--color-line))",
                    borderRadius: 12,
                    boxShadow:
                      "0 10px 15px -3px rgba(0, 0, 0, 0.1)",
                    fontSize: 12,
                  }}
                />

                <Line
                  type="monotone"
                  dataKey="value"
                  stroke="#3F8C84"
                  strokeWidth={3}
                  dot={{
                    r: 4,
                    fill: "#3F8C84",
                    strokeWidth: 2,
                    stroke: "#ffffff",
                  }}
                  activeDot={{
                    r: 6,
                    strokeWidth: 0,
                  }}
                />

              </LineChart>
            </ResponsiveContainer>
          )}

        </Card>
      </div>


      {/* =====================================================
          PRIORITY BREAKDOWN
          ===================================================== */}
      {priorityData.length > 0 && (
        <Card className="p-5 border border-line/60 rounded-2xl shadow-sm md:w-1/2">

          <div>

            <h3 className="font-display text-lg font-medium">
              Encounter Priority Breakdown
            </h3>

            <p className="text-xs text-ink/50">
              Categorization by urgency level
            </p>

          </div>

          <div className="flex items-center justify-between mt-2">

            <ResponsiveContainer width="100%" height={180}>

              <PieChart>

                <Pie
                  data={priorityData}
                  dataKey="value"
                  nameKey="name"
                  innerRadius={50}
                  outerRadius={75}
                  paddingAngle={4}
                >
                  {priorityData.map((entry, i) => (
                    <Cell
                      key={entry.name}
                      fill={
                        COLOR_PALETTE[
                          i % COLOR_PALETTE.length
                        ]
                      }
                      cornerRadius={4}
                    />
                  ))}
                </Pie>

                <Tooltip
                  contentStyle={{
                    background:
                      "rgba(var(--color-surface), 0.95)",
                    border:
                      "1px solid rgb(var(--color-line))",
                    borderRadius: 8,
                    fontSize: 12,
                  }}
                />

              </PieChart>
            </ResponsiveContainer>

            <div className="flex flex-col gap-2 min-w-[130px] pr-4">

              {priorityData.map((item, index) => (
                <div
                  key={item.name}
                  className="flex items-center justify-between text-xs"
                >

                  <div className="flex items-center gap-2">

                    <span
                      className="w-2.5 h-2.5 rounded-full"
                      style={{
                        backgroundColor:
                          COLOR_PALETTE[
                            index % COLOR_PALETTE.length
                          ],
                      }}
                    />

                    <span className="text-ink/70 capitalize">
                      {item.name}
                    </span>

                  </div>

                  <span className="font-mono font-semibold">
                    {item.value}
                  </span>

                </div>
              ))}

            </div>
          </div>
        </Card>
      )}

    </div>
  );
}


/* =========================================================
   STAT CARD
   ========================================================= */

function StatCard({
  label,
  value,
  tone,
  icon: Icon,
  trend,
}) {
  const iconTones = {
    neutral:
      "bg-teal-500/10 text-teal-600 dark:text-teal-400",

    teal:
      "bg-teal-500/10 text-teal-600 dark:text-teal-400",

    success:
      "bg-emerald-500/10 text-emerald-600",

    warning:
      "bg-amber-500/10 text-amber-600",

    critical:
      "bg-rose-500/10 text-rose-600",
  };

  return (
    <Card className="p-4 border border-line/60 rounded-2xl hover:border-teal-500/30 transition-all duration-200 hover:-translate-y-0.5 shadow-sm">

      <div className="flex items-start justify-between mb-3">

        <p className="text-xs font-medium text-ink/60 leading-snug pr-2">
          {label}
        </p>

        {Icon && (
          <div
            className={`h-8 w-8 rounded-xl flex items-center justify-center shrink-0 ${
              iconTones[tone || "neutral"]
            }`}
          >
            <Icon size={16} strokeWidth={2} />
          </div>
        )}

      </div>

      <div className="flex items-baseline justify-between">

        <p className="font-display text-2xl font-bold tracking-tight">
          {value}
        </p>

        {trend && (
          <span className="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-md">
            {trend}
          </span>
        )}

      </div>

      {tone &&
        tone !== "neutral" &&
        tone !== "teal" && (
          <div className="mt-2.5">

            <Badge tone={tone}>
              {tone === "critical"
                ? "Needs Attention"
                : tone === "warning"
                ? "Monitor"
                : "Normal"}
            </Badge>

          </div>
        )}

    </Card>
  );
}