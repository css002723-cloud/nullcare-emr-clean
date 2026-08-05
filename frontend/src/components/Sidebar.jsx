import { NavLink } from "react-router-dom";
import {
  LayoutDashboard, Users, ClipboardList, Stethoscope, Activity, FlaskConical,
  ScanLine, Pill, BedDouble, Droplets, Receipt, FileSearch, ShieldCheck,
  ScrollText, LogOut, Sun, Moon, X, MessageSquareText, Siren, Package, Settings as SettingsIcon,
  CalendarDays,
} from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";
import { useUnreadMessages } from "../hooks/useUnreadMessages";
import { useMyAppointments } from "../hooks/useMyAppointments";

const NAV_BY_ROLE = {
  admin: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/appointments", label: "Appointments", icon: CalendarDays },
    { to: "/reception", label: "Reception", icon: ClipboardList },
    { to: "/triage", label: "Triage & Nursing", icon: Activity },
    { to: "/consultation", label: "Consultation", icon: Stethoscope },
    { to: "/laboratory", label: "Laboratory", icon: FlaskConical },
    // { to: "/imaging", label: "Imaging", icon: ScanLine },
    { to: "/pharmacy", label: "Pharmacy", icon: Pill },
    { to: "/wards", label: "Wards", icon: BedDouble },
    { to: "/icu", label: "ICU & HDU", icon: Siren },
    { to: "/dialysis", label: "Dialysis", icon: Droplets },
    { to: "/billing", label: "Billing", icon: Receipt },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
    { to: "/research", label: "Research Export", icon: FileSearch },
    { to: "/admin/users", label: "User Management", icon: ShieldCheck },
    { to: "/admin/audit", label: "Audit Trail", icon: ScrollText },
  ],
  reception: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/appointments", label: "Appointments", icon: CalendarDays },
    { to: "/reception", label: "Reception", icon: ClipboardList },
  ],
  nurse: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/appointments", label: "Appointments", icon: CalendarDays },
    { to: "/triage", label: "Triage & Nursing", icon: Activity },
    { to: "/wards", label: "Wards", icon: BedDouble },
    { to: "/icu", label: "ICU & HDU", icon: Siren },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
  ],
  doctor: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/appointments", label: "Appointments", icon: CalendarDays },
    { to: "/consultation", label: "Consultation", icon: Stethoscope },
    { to: "/wards", label: "Wards", icon: BedDouble },
    { to: "/icu", label: "ICU & HDU", icon: Siren },
  ],
  lab_tech: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/laboratory", label: "Laboratory", icon: FlaskConical },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
  ],
  radiologist: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/imaging", label: "Imaging", icon: ScanLine },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
  ],
  pharmacist: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/pharmacy", label: "Pharmacy", icon: Pill },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
  ],
  billing: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/billing", label: "Billing", icon: Receipt },
  ],
  dialysis_tech: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/dialysis", label: "Dialysis", icon: Droplets },
    { to: "/inventory", label: "Inventory & Equipment", icon: Package },
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
  const { unreadCount } = useUnreadMessages();
  const { scheduledCount } = useMyAppointments();
  const roleItems = NAV_BY_ROLE[user?.role] || [];

  const items = [
    ...roleItems.slice(0, 1),
    { to: "/messages", label: "Messages", icon: MessageSquareText, badge: unreadCount },
    ...roleItems.slice(1),
  ].map((item) => (item.to === "/appointments" ? { ...item, badge: scheduledCount } : item));

  return (
    <>
      {/* Mobile overlay backdrop */}
      {open && (
        <div
          className="fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-40 md:hidden transition-opacity"
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      <nav
        aria-label="Main navigation"
        className={`w-64 shrink-0 bg-teal-800 text-teal-50 min-h-screen flex flex-col no-print
          fixed md:sticky top-0 left-0 z-50 md:z-auto h-screen md:h-auto border-r border-teal-700/50 shadow-xl
          transition-transform duration-200 ease-out
          ${open ? "translate-x-0" : "-translate-x-full md:translate-x-0"}`}
      >
        {/* BRANDING HEADER CONTAINER */}
        <div className="px-5 py-5 border-b border-teal-700/60 flex items-center justify-between">
          <div className="min-w-0 flex-1 space-y-1">
            {/* REPLACED HEART ICON AND TEXT WITH IMAGE BRAND MARK */}
            <img 
              src="/nullcare.png"
              alt="nullcare logo" 
              className="h-8 w-auto object-contain brightness-0 invert" 
            />
            <p className="text-[11px] text-teal-200/80 font-medium tracking-wide">MUST Teaching Hospital EMR</p>
          </div>
          <button
            onClick={onClose}
            aria-label="Close menu"
            className="md:hidden h-8 w-8 rounded-lg flex items-center justify-center text-teal-100 hover:bg-teal-700/60 transition-colors shrink-0"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
          {items.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={onClose}
                className={({ isActive }) =>
                  `group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150 ${
                    isActive
                      ? "bg-white/10 text-white shadow-sm backdrop-blur-md border border-white/10 font-semibold"
                      : "text-teal-100/80 hover:bg-teal-700/50 hover:text-white"
                  }`
                }
              >
                {({ isActive }) => (
                  <>
                    {isActive && (
                      <span className="absolute left-0 top-2 bottom-2 w-1 bg-teal-300 rounded-r-full" />
                    )}
                    <Icon size={18} strokeWidth={isActive ? 2.2 : 2} className="shrink-0 transition-transform duration-150 group-hover:scale-105" />
                    <span className="flex-1 truncate">{item.label}</span>
                    {item.badge > 0 && (
                      <span className="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center shrink-0 shadow-sm">
                        {item.badge > 9 ? "9+" : item.badge}
                      </span>
                    )}
                  </>
                )}
              </NavLink>
            );
          })}
        </div>

        <div className="px-4 py-4 border-t border-teal-700/60 bg-teal-900/30 space-y-3">
          {/* Settings Link */}
          <NavLink
            to="/settings"
            onClick={onClose}
            className={({ isActive }) =>
              `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-150 ${
                isActive
                  ? "bg-white text-teal-700 shadow-sm"
                  : "text-teal-100 hover:bg-teal-500/50"
              }`
            }
          >
            <SettingsIcon
              size={17}
              strokeWidth={2}
              className="shrink-0"
            />
            <span className="flex-1">Settings</span>
          </NavLink>

          <button
            onClick={toggleTheme}
            aria-label={theme === "dark" ? "Switch to light mode" : "Switch to dark mode"}
            className="w-full flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium text-teal-100 hover:bg-teal-500/50 transition-colors"
          >
            <span className="flex items-center gap-2.5">
              <Moon size={17} strokeWidth={2} />
              {"Dark mode"}
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

          <div className="p-2.5 rounded-xl bg-teal-900/40 border border-teal-700/40 flex items-center gap-3">
            <div className="h-9 w-9 rounded-lg bg-teal-500/20 text-teal-200 border border-teal-500/30 flex items-center justify-center text-sm font-bold shrink-0">
              {user?.full_name?.[0]}
            </div>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-white truncate leading-tight">{user?.full_name}</p>
              <p className="text-xs text-teal-200/70 capitalize truncate mt-0.5">{user?.role?.replace("_", " ")}</p>
            </div>
          </div>

          <button
            onClick={logout}
            className="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-semibold text-teal-100/90 hover:text-white hover:bg-rose-500/20 transition-colors"
          >
            <LogOut size={14} strokeWidth={2.25} />
            Sign out
          </button>
        </div>
      </nav>
    </>
  );
}