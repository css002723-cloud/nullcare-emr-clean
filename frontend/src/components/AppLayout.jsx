import { useState } from "react";
import { Menu, HeartPulse } from "lucide-react";
import Sidebar from "./Sidebar";
import SyncStatusBar from "./SyncStatusBar";

export default function AppLayout({ children }) {
  const [menuOpen, setMenuOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-paper">
      <a href="#main-content" className="skip-link">Skip to main content</a>

      <Sidebar open={menuOpen} onClose={() => setMenuOpen(false)} />

      <div className="flex-1 min-w-0">
        {/* Mobile-only top bar */}
        <header className="md:hidden sticky top-0 z-30 flex items-center gap-3 bg-teal-600 text-white px-4 py-3 no-print">
          <button
            onClick={() => setMenuOpen(true)}
            aria-label="Open menu"
            className="h-9 w-9 rounded-lg flex items-center justify-center hover:bg-teal-500/50 shrink-0 -ml-1"
          >
            <Menu size={20} />
          </button>
          <HeartPulse size={18} strokeWidth={2.25} className="text-teal-100 shrink-0" />
          <p className="font-display text-lg leading-none">nullcare</p>
        </header>

        <SyncStatusBar />
        <main id="main-content" className="px-4 sm:px-6 md:px-8 py-5 md:py-8 max-w-6xl mx-auto">
          {children}
        </main>
      </div>
    </div>
  );
}
