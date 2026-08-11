import { NavLink } from "react-router-dom";
import { useEffect, useState } from "react";
import {
  LayoutDashboard,
  Users,
  ClipboardList,
  Stethoscope,
  Activity,
  FlaskConical,
  ScanLine,
  Pill,
  BedDouble,
  Droplets,
  Receipt,
  FileSearch,
  ShieldCheck,
  ScrollText,
  LogOut,
  Moon,
  Sun,
  X,
  Bell,
  Siren,
  Package,
  Settings as SettingsIcon,
  CalendarDays,
  ChevronsLeft,
  ChevronsRight,
} from "lucide-react";

import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";
import { useUnreadMessages } from "../hooks/useUnreadMessages";
import { useMyAppointments } from "../hooks/useMyAppointments";
import api from "../services/api";

const NAV_BY_ROLE = {
  admin: [
    { to: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { to: "/patients", label: "Patients", icon: Users },
    { to: "/appointments", label: "Appointments", icon: CalendarDays },
    { to: "/reception", label: "Reception", icon: ClipboardList },
    { to: "/triage", label: "Triage & Nursing", icon: Activity },
    { to: "/consultation", label: "Consultation", icon: Stethoscope },
    { to: "/laboratory", label: "Laboratory", icon: FlaskConical },

    // Uncomment when Imaging is ready
    { to: "/imaging", label: "Imaging", icon: ScanLine },

    { to: "/pharmacy", label: "Pharmacy", icon: Pill },
    { to: "/wards", label: "Wards", icon: BedDouble },
    { to: "/icu", label: "ICU & HDU", icon: Siren },
    { to: "/dialysis", label: "Dialysis", icon: Droplets },
    { to: "/billing", label: "Billing", icon: Receipt },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
    {
      to: "/research",
      label: "Research Export",
      icon: FileSearch,
    },
    {
      to: "/admin/users",
      label: "User Management",
      icon: ShieldCheck,
    },
    {
      to: "/admin/audit",
      label: "Audit Trail",
      icon: ScrollText,
    },
  ],

  reception: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/patients",
      label: "Patients",
      icon: Users,
    },
    {
      to: "/appointments",
      label: "Appointments",
      icon: CalendarDays,
    },
    {
      to: "/reception",
      label: "Reception",
      icon: ClipboardList,
    },
  ],

  nurse: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/patients",
      label: "Patients",
      icon: Users,
    },
    {
      to: "/appointments",
      label: "Appointments",
      icon: CalendarDays,
    },
    {
      to: "/triage",
      label: "Triage & Nursing",
      icon: Activity,
    },
    {
      to: "/wards",
      label: "Wards",
      icon: BedDouble,
    },
    {
      to: "/icu",
      label: "ICU & HDU",
      icon: Siren,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
  ],

  doctor: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/patients",
      label: "Patients",
      icon: Users,
    },
    {
      to: "/appointments",
      label: "Appointments",
      icon: CalendarDays,
    },
    {
      to: "/consultation",
      label: "Consultation",
      icon: Stethoscope,
    },
    {
      to: "/imaging",
      label: "Imaging",
      icon: ScanLine,
    },
    {
      to: "/wards",
      label: "Wards",
      icon: BedDouble,
    },
    {
      to: "/icu",
      label: "ICU & HDU",
      icon: Siren,
    },
  ],

  lab_tech: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/laboratory",
      label: "Laboratory",
      icon: FlaskConical,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
  ],

  radiologist: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/imaging",
      label: "Imaging",
      icon: ScanLine,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
  ],

  pharmacist: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/pharmacy",
      label: "Pharmacy",
      icon: Pill,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
  ],

  billing: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/billing",
      label: "Billing",
      icon: Receipt,
    },
  ],

  dialysis_tech: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/dialysis",
      label: "Dialysis",
      icon: Droplets,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
  ],

  records_officer: [
    {
      to: "/dashboard",
      label: "Dashboard",
      icon: LayoutDashboard,
    },
    {
      to: "/patients",
      label: "Patients",
      icon: Users,
    },
    {
      to: "/inventory",
      label: "Inventory & Equipment",
      icon: Package,
    },
    {
      to: "/research",
      label: "Research Export",
      icon: FileSearch,
    },
  ],
};

export default function Sidebar({ open, onClose }) {
  const { user, logout } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const [collapsed, setCollapsed] = useState(false);
  const [consultationEmergencyCount, setConsultationEmergencyCount] = useState(0);

  const { unreadCount } = useUnreadMessages();
  const { scheduledCount } = useMyAppointments();

  const roleItems = NAV_BY_ROLE[user?.role] || [];

  useEffect(() => {
    api
      .get("/encounters", { params: { department: "consultation", active_only: "true" } })
      .then((res) => {
        const data = Array.isArray(res.data) ? res.data : [];
        setConsultationEmergencyCount(data.filter((enc) => enc.is_emergency).length);
      })
      .catch(() => {
        setConsultationEmergencyCount(0);
      });
  }, []);

  const items = [
    ...roleItems.slice(0, 1),

    {
      to: "/messages",
      label: "Notifications",
      icon: Bell,
      badge: unreadCount,
    },

    ...roleItems.slice(1),
  ]
    .map((item) =>
      item.to === "/appointments"
        ? {
            ...item,
            badge: scheduledCount,
          }
        : item
    )
    .map((item) =>
      item.to === "/consultation"
        ? {
            ...item,
            emergency: consultationEmergencyCount,
          }
        : item
    );

  const isDark = theme === "dark";

  return (
    <>
      {/* =====================================================
          MOBILE OVERLAY
      ====================================================== */}
      {open && (
        <div
          className={`
            fixed
            inset-0
            z-40
            md:hidden

            backdrop-blur-sm

            ${
              isDark
                ? "bg-black/40"
                : "bg-teal-950/20"
            }
          `}
          onClick={onClose}
          aria-hidden="true"
        />
      )}

      {/* =====================================================
          SIDEBAR
      ====================================================== */}
      <nav
        aria-label="Main navigation"
        className={`
          fixed
          md:sticky

          top-0
          left-0

          z-50
          md:z-auto

          w-64
          shrink-0
          ${collapsed ? "md:w-20" : "md:w-64"}

          h-screen
          md:h-auto

          min-h-screen

          flex
          flex-col

          no-print

          border-r

          transition-all
          duration-200
          ease-out

          ${
            isDark
              ? `
                bg-teal-900
                text-teal-50
                border-teal-800
              `
              : `
                bg-white
                text-teal-800
                border-slate-200
              `
          }

          ${
            open
              ? "translate-x-0"
              : "-translate-x-full md:translate-x-0"
          }
        `}
      >

        {/* =====================================================
            BRANDING
        ====================================================== */}
        <div
          className={`
            px-5
            py-5

            border-b

            flex
            items-center
            justify-between

            ${
              isDark
                ? "border-teal-800"
                : "border-slate-100"
            }
          `}
        >
          <div className="min-w-0 flex-1">

            <img
              src={`${import.meta.env.BASE_URL}nullcare.png`}
              alt="NullCare logo"
              className="
                h-8
                w-auto
                object-contain
              "
            />

            {!collapsed && (
              <p
                className={`
                  mt-2

                  text-[11px]

                  font-medium

                  tracking-wide

                  ${
                    isDark
                      ? "text-teal-200/70"
                      : "text-teal-700/70"
                  }
                `}
              >
                MUST Teaching Hospital EMR
              </p>
            )}

          </div>

          <button
            type="button"
            onClick={() => setCollapsed((value) => !value)}
            aria-label={collapsed ? "Expand sidebar" : "Collapse sidebar"}
            className={
              `hidden md:inline-flex h-8 w-8 rounded-lg items-center justify-center transition-colors ${
                isDark
                  ? "text-teal-200 hover:bg-teal-800 hover:text-white"
                  : "text-teal-700 hover:bg-teal-50 hover:text-teal-900"
              }`
            }
          >
            {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
          </button>

          {/* Mobile close */}
          <button
            onClick={onClose}
            aria-label="Close menu"
            className={`
              md:hidden

              h-8
              w-8

              rounded-lg

              flex
              items-center
              justify-center

              transition-colors

              ${
                isDark
                  ? `
                    text-teal-200
                    hover:bg-teal-800
                    hover:text-white
                  `
                  : `
                    text-teal-700
                    hover:bg-teal-50
                    hover:text-teal-900
                  `
              }
            `}
          >
            <X size={18} />
          </button>

        </div>


        {/* =====================================================
            NAVIGATION
        ====================================================== */}
        <div
          className="
            flex-1

            py-4
            px-3

            overflow-y-auto

            space-y-1
          "
        >

          {items.map((item) => {

            const Icon = item.icon;

            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={onClose}
                title={collapsed ? item.label : undefined}
                aria-label={collapsed ? item.label : undefined}
                className={({ isActive }) => `
                  group
                  relative

                  flex
                  items-center
                  ${collapsed ? "justify-center" : "gap-3"}

                  rounded-xl

                  ${collapsed ? "px-0" : "px-3"}
                  py-2.5

                  text-sm
                  font-medium

                  transition-all
                  duration-150

                  ${
                    isDark
                      ? isActive
                        ? `
                          bg-teal-700
                          text-white
                          font-semibold

                          shadow-sm
                        `
                        : `
                          text-teal-100

                          hover:bg-teal-800
                          hover:text-white
                        `
                      : isActive
                        ? `
                          bg-teal-50
                          text-teal-800
                          font-semibold
                        `
                        : `
                          text-teal-800

                          hover:bg-teal-50
                          hover:text-teal-900
                        `
                  }
                `}
              >

                {({ isActive }) => (
                  <>

                    {/* =====================================
                        ACTIVE INDICATOR
                    ====================================== */}
                    {isActive && (
                      <span
                        className={`
                          absolute

                          left-0

                          top-2
                          bottom-2

                          w-1

                          rounded-r-full

                          ${
                            isDark
                              ? "bg-teal-200"
                              : "bg-teal-700"
                          }
                        `}
                      />
                    )}


                    {/* =====================================
                        ICON
                    ====================================== */}
                    <Icon
                      size={18}
                      strokeWidth={
                        isActive ? 2.2 : 2
                      }
                      className={`
                        shrink-0

                        transition-all
                        duration-150

                        ${
                          item.emergency > 0
                            ? "text-alert group-hover:text-alert/80"
                            : isDark
                              ? isActive
                                ? "text-white"
                                : "text-teal-200 group-hover:text-white"
                              : isActive
                                ? "text-teal-700"
                                : "text-teal-700 group-hover:text-teal-900"
                        }

                        group-hover:scale-105
                      `}
                    />

                    {item.emergency > 0 && collapsed && (
                      <span className="absolute right-3 top-3 h-2.5 w-2.5 rounded-full bg-alert ring-1 ring-white/80" />
                    )}

                    {/* =====================================
                        LABEL
                    ====================================== */}
                    {!collapsed && (
                      <span className="flex-1 truncate">
                        {item.label}
                      </span>
                    )}

                    {item.emergency > 0 && !collapsed && (
                      <span className="inline-flex items-center gap-1 rounded-full bg-alert/10 text-alert border border-alert/30 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em]">
                        <Siren size={12} strokeWidth={2} />
                        {item.emergency > 1 ? `${item.emergency} emergencies` : "Emergency"}
                      </span>
                    )}


                    {/* =====================================
                        BADGE
                    ====================================== */}
                    {item.badge > 0 && !collapsed && (
                      <span
                        className={`
                          min-w-[1.25rem]
                          h-5

                          px-1.5

                          rounded-full

                          text-white

                          text-[10px]
                          font-bold

                          flex
                          items-center
                          justify-center

                          shrink-0

                          ${
                            isDark
                              ? "bg-teal-600"
                              : "bg-teal-700"
                          }
                        `}
                      >
                        {item.badge > 9
                          ? "9+"
                          : item.badge}
                      </span>
                    )}

                  </>
                )}

              </NavLink>
            );
          })}

        </div>


        {/* =====================================================
            BOTTOM SECTION
        ====================================================== */}
        <div
          className={`
            px-4
            py-4

            border-t

            space-y-3

            ${
              isDark
                ? `
                  bg-teal-900
                  border-teal-800
                `
                : `
                  bg-white
                  border-slate-100
                `
            }
          `}
        >

          {/* ===================================================
              SETTINGS
          ==================================================== */}
          <NavLink
            to="/settings"
            onClick={onClose}
            title={collapsed ? "Settings" : undefined}
            className={({ isActive }) => `
              flex
              items-center
              ${collapsed ? "justify-center" : "gap-2.5"}

              rounded-lg

              ${collapsed ? "px-0" : "px-3"}
              py-2

              text-sm
              font-medium

              transition-colors

              ${
                isDark
                  ? isActive
                    ? `
                      bg-teal-800
                      text-white
                      font-semibold
                    `
                    : `
                      text-teal-100
                      hover:bg-teal-800
                      hover:text-white
                    `
                  : isActive
                    ? `
                      bg-teal-50
                      text-teal-800
                      font-semibold
                    `
                    : `
                      text-teal-800
                      hover:bg-teal-50
                      hover:text-teal-900
                    `
              }
            `}
          >

            <SettingsIcon
              size={17}
              strokeWidth={2}
              className="shrink-0"
            />

            {!collapsed && (
              <span>
                Settings
              </span>
            )}

          </NavLink>


          {/* ===================================================
              THEME TOGGLE
          ==================================================== */}
          <button
            onClick={toggleTheme}
            title={collapsed ? (isDark ? "Switch to light mode" : "Switch to dark mode") : undefined}
            aria-label={
              isDark
                ? "Switch to light mode"
                : "Switch to dark mode"
            }
            className={`
              w-full

              flex
              items-center
              ${collapsed ? "justify-center" : "justify-between"}
              gap-2

              rounded-lg

              ${collapsed ? "px-0" : "px-3"}
              py-2

              text-sm
              font-medium

              transition-colors

              ${
                isDark
                  ? `
                    text-teal-100
                    hover:bg-teal-800
                    hover:text-white
                  `
                  : `
                    text-teal-800
                    hover:bg-teal-50
                    hover:text-teal-900
                  `
              }
            `}
          >

            <span className={collapsed ? "flex items-center justify-center" : "flex items-center gap-2.5"}>

              {isDark ? (
                <Sun
                  size={17}
                  strokeWidth={2}
                />
              ) : (
                <Moon
                  size={17}
                  strokeWidth={2}
                />
              )}

              {!collapsed && (
                isDark
                  ? "Light mode"
                  : "Dark mode"
              )}

            </span>


            {/* =============================================
                TOGGLE SWITCH
            ============================================== */}
            <span
              className={`
                relative

                inline-flex

                h-5
                w-9

                items-center

                rounded-full

                transition-colors
                duration-200

                ${
                  isDark
                    ? "bg-teal-700"
                    : "bg-slate-300"
                }
              `}
            >

              <span
                className={`
                  inline-block

                  h-3.5
                  w-3.5

                  rounded-full

                  bg-white

                  shadow-sm

                  transition-transform
                  duration-200

                  ${
                    isDark
                      ? "translate-x-[18px]"
                      : "translate-x-[4px]"
                  }
                `}
              />

            </span>

          </button>


          {/* ===================================================
              USER PROFILE
          ==================================================== */}
          <div
            className={`
              p-2.5

              rounded-xl

              border

              flex
              items-center
              ${collapsed ? "justify-center" : "gap-3"}

              ${collapsed ? "flex-col" : "flex-row"}

              ${
                isDark
                  ? `
                    bg-teal-800
                    border-teal-700
                  `
                  : `
                    bg-teal-50
                    border-teal-100
                  `
              }
            `}
            title={collapsed ? user?.full_name || "User" : undefined}
          >

            {/* Avatar */}
            <div
              className={`
                h-9
                w-9

                rounded-lg

                flex
                items-center
                justify-center

                text-sm
                font-bold

                shrink-0

                border

                ${
                  isDark
                    ? `
                      bg-teal-700
                      text-white
                      border-teal-600
                    `
                    : `
                      bg-white
                      text-teal-800
                      border-teal-100
                      shadow-sm
                    `
                }
              `}
            >
              {user?.full_name?.[0] || "U"}
            </div>


            {!collapsed && (
              <div className="min-w-0 flex-1">

                <p
                  className={`
                    text-sm

                    font-semibold

                    truncate

                    leading-tight

                    ${
                      isDark
                        ? "text-white"
                        : "text-teal-900"
                    }
                  `}
                >
                  {user?.full_name || "User"}
                </p>

                <p
                  className={`
                    text-xs

                    capitalize

                    truncate

                    mt-0.5

                    ${
                      isDark
                        ? "text-teal-200/70"
                        : "text-teal-700/70"
                    }
                  `}
                >
                  {user?.role?.replace("_", " ") || "User"}
                </p>

              </div>
            )}

          </div>


          {/* ===================================================
              LOGOUT
          ==================================================== */}
          <button
            onClick={logout}
            title={collapsed ? "Sign out" : undefined}
            aria-label="Sign out"
            className={`
              w-full

              flex
              items-center
              justify-center
              gap-2

              ${collapsed ? "px-0" : "px-3"}
              py-2

              rounded-lg

              text-xs
              font-semibold

              transition-colors

              ${
                isDark
                  ? `
                    text-teal-200

                    hover:text-red-300
                    hover:bg-teal-800
                  `
                  : `
                    text-teal-700

                    hover:text-red-600
                    hover:bg-red-50
                  `
              }
            `}
          >

            <LogOut
              size={14}
              strokeWidth={2.25}
            />

            {!collapsed && "Sign out"}

          </button>

        </div>

      </nav>
    </>
  );
}