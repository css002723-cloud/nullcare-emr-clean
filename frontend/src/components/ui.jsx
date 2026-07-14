import { Loader2, Inbox } from "lucide-react";

export function Badge({ children, tone = "neutral", icon: Icon, className = "" }) {
  const tones = {
    neutral: "bg-surface-alt text-accent border-line",
    success: "bg-moss/10 text-moss border-moss/25 dark:bg-moss/15",
    warning: "bg-clay/10 text-clay border-clay/30 dark:bg-clay/15",
    critical: "bg-alert/10 text-alert border-alert/30 dark:bg-alert/15",
    muted: "bg-line/40 text-ink/60 border-line",
  };
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ${tones[tone]} ${className}`}
    >
      {Icon && <Icon size={12} strokeWidth={2.5} />}
      {children}
    </span>
  );
}

export function Button({ children, variant = "primary", size = "md", icon: Icon, className = "", ...props }) {
  const base =
    "inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-offset-2 active:scale-[0.98]";
  const variants = {
    primary: "bg-teal-500 text-white hover:bg-teal-600 shadow-card hover:shadow-md",
    secondary: "bg-surface text-teal-700 dark:text-teal-200 border border-teal-200 dark:border-teal-500/40 hover:bg-teal-50 dark:hover:bg-teal-500/10",
    ghost: "text-teal-700 dark:text-teal-200 hover:bg-teal-50 dark:hover:bg-teal-500/10",
    danger: "bg-alert text-white hover:bg-alert/90 shadow-card",
    clay: "bg-clay text-white hover:bg-clay/90 shadow-card",
  };
  const sizes = { sm: "text-sm px-3 py-1.5", md: "text-sm px-4 py-2.5", lg: "text-base px-5 py-3" };
  return (
    <button className={`${base} ${variants[variant]} ${sizes[size]} ${className}`} {...props}>
      {Icon && <Icon size={size === "sm" ? 14 : 16} strokeWidth={2.25} />}
      {children}
    </button>
  );
}

export function Card({ children, className = "" }) {
  return (
    <div className={`bg-surface rounded-2xl border border-line shadow-card transition-colors duration-200 p-5 ${className}`}>
      {children}
    </div>
  );
}

export function Field({ label, children, required, hint, className = "" }) {
  return (
    <label className={`block ${className}`}>
      <span className="text-sm font-medium text-ink/80 mb-1 block">
        {label} {required && <span className="text-alert">*</span>}
      </span>
      {children}
      {hint && <span className="text-xs text-ink/50 mt-1 block">{hint}</span>}
    </label>
  );
}

export const inputClass =
  "w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink/35 focus:border-teal-400 focus:ring-2 focus:ring-teal-400/20 outline-none transition-colors";

export function Input({ className = "", ...props }) {
  return <input className={`${inputClass} ${className}`} {...props} />;
}

export function Select({ children, className = "", ...props }) {
  return (
    <select className={`${inputClass} ${className}`} {...props}>
      {children}
    </select>
  );
}

export function Textarea({ className = "", ...props }) {
  return <textarea className={`${inputClass} min-h-[90px] ${className}`} {...props} />;
}

export function EmptyState({ title, hint, action, icon: Icon = Inbox }) {
  return (
    <div className="text-center py-14 px-6">
      <div className="mx-auto mb-3 h-12 w-12 rounded-full bg-surface-alt flex items-center justify-center text-accent/60">
        <Icon size={22} strokeWidth={1.75} />
      </div>
      <p className="font-display text-lg text-ink/70">{title}</p>
      {hint && <p className="text-sm text-ink/45 mt-1 max-w-sm mx-auto">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}

export function LoadingRow({ label = "Loading…" }) {
  return (
    <div className="flex items-center gap-2 text-sm text-ink/50 py-6 justify-center">
      <Loader2 size={16} className="animate-spin text-teal-400" />
      {label}
    </div>
  );
}

export function priorityTone(priority) {
  if (priority === "emergency" || priority === "stat") return "critical";
  if (priority === "urgent") return "warning";
  return "neutral";
}

export function calcAge(dob, estimatedAge) {
  if (dob) {
    const diff = Date.now() - new Date(dob).getTime();
    return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
  }
  return estimatedAge ?? null;
}
