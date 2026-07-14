import { NavLink } from "react-router-dom";
import {
  LayoutDashboard, Users, ClipboardList, Stethoscope, Activity, FlaskConical,
  ScanLine, Pill, BedDouble, Droplets, Receipt, FileSearch, ShieldCheck,
  ScrollText, LogOut, Sun, Moon, HeartPulse, X,
} from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";

const NAV_BY_ROLE = {
  admin: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/reception", label: "Reception", icon: ClipboardList },
    { to: "/triage", label: "Triage & Nursing", icon: Activity },
    { to: "/consultation", label: "Consultation", icon: Stethoscope },
    { to: "/laboratory", label: "Laboratory", icon: FlaskConical },
    { to: "/imaging", label: "Imaging", icon: ScanLine },
    { to: "/pharmacy", label: "Pharmacy", icon: Pill },
    { to: "/wards", label: "Wards", icon: BedDouble },
    { to: "/dialysis", label: "Dialysis", icon: Droplets },
    { to: "/billing", label: "Billing", icon: Receipt },
    { to: "/research", label: "Research Export", icon: FileSearch },
    { to: "/admin/users", label: "User Management", icon: ShieldCheck },
    { to: "/admin/audit", label: "Audit Trail", icon: ScrollText },
  ],
  reception: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/reception", label: "Reception", icon: ClipboardList },
  ],
  nurse: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/triage", label: "Triage & Nursing", icon: Activity },
    { to: "/wards", label: "Wards", icon: BedDouble },
  ],
  doctor: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/consultation", label: "Consultation", icon: Stethoscope },
    { to: "/wards", label: "Wards", icon: BedDouble },
  ],
  lab_tech: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/laboratory", label: "Laboratory", icon: FlaskConical },
  ],
  radiologist: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/imaging", label: "Imaging", icon: ScanLine },
  ],
  pharmacist: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/pharmacy", label: "Pharmacy", icon: Pill },
  ],
  billing: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/billing", label: "Billing", icon: Receipt },
  ],
  dialysis_tech: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/dialysis", label: "Dialysis", icon: Droplets },
  ],
  records_officer: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/research", label: "Research Export", icon: FileSearch },
  ],
};

export default function Sidebar({ open, onClose }) {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const items = NAV_BY_ROLE[user?.role] || [];

  return (
    <>
      {/* Mobile overlay backdrop */}
      {open && (
        <div
          className="fixed inset-0 bg-black/50 z-40 md:hidden"
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      <nav
        aria-label="Main navigation"
        className={`w-64 shrink-0 bg-teal-600 text-teal-50 min-h-screen flex flex-col no-print
          fixed md:sticky top-0 left-0 z-50 md:z-auto h-screen md:h-auto
          transition-transform duration-200 ease-out
          ${open ? "translate-x-0" : "-translate-x-full md:translate-x-0"}`}
      >
        <div className="px-5 py-6 border-b border-teal-500/40 flex items-center gap-2.5">
          <div className="h-9 w-9 rounded-xl bg-white/10 flex items-center justify-center shrink-0">
            <HeartPulse size={20} strokeWidth={2.25} className="text-teal-100" />
          </div>
          <div className="min-w-0 flex-1">
            <p className="font-display text-2xl text-white leading-tight">nullcare</p>
            <p className="text-[11px] text-teal-200 -mt-0.5">MUST Teaching Hospital EMR</p>
          </div>
          <button
            onClick={onClose}
            aria-label="Close menu"
            className="md:hidden h-8 w-8 rounded-lg flex items-center justify-center text-teal-100 hover:bg-teal-500/50 shrink-0"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex-1 py-4 px-2 space-y-0.5 overflow-y-auto">
          {items.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={onClose}
                className={({ isActive }) =>
                  `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ${
                    isActive ? "bg-white text-teal-700 shadow-sm" : "text-teal-100 hover:bg-teal-500/50"
                  }`
                }
              >
                <Icon size={17} strokeWidth={2} className="shrink-0" />
                {item.label}
              </NavLink>
            );
          })}
        </div>

        <div className="px-4 py-4 border-t border-teal-500/40 space-y-3">
          <button
            onClick={toggleTheme}
            aria-label={theme === "dark" ? "Switch to light mode" : "Switch to dark mode"}
            className="w-full flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium text-teal-100 hover:bg-teal-500/50 transition-colors"
          >
            <span className="flex items-center gap-2.5">
              {theme === "dark" ? <Moon size={17} strokeWidth={2} /> : <Sun size={17} strokeWidth={2} />}
              {theme === "dark" ? "Dark mode" : "Light mode"}
            </span>
            <span
              className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                theme === "dark" ? "bg-teal-300" : "bg-teal-500/60"
              }`}
            >
              <span
                className="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform"
                style={{ transform: theme === "dark" ? "translateX(18px)" : "translateX(4px)" }}
              />
            </span>
          </button>

          <div className="flex items-center gap-2.5">
            <div className="h-8 w-8 rounded-full bg-white/15 flex items-center justify-center text-sm font-semibold text-white shrink-0">
              {user?.full_name?.[0]}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-semibold text-white truncate">{user?.full_name}</p>
              <p className="text-xs text-teal-200 capitalize">{user?.role?.replace("_", " ")}</p>
            </div>
          </div>
          <button
            onClick={logout}
            className="flex items-center gap-2 text-xs font-semibold text-teal-100 hover:text-white transition-colors"
          >
            <LogOut size={14} strokeWidth={2.25} />
            Sign out
          </button>
        </div>
      </nav>
    </>
  );
}
