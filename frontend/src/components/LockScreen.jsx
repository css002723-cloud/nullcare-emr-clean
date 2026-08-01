import { useState } from "react";
import { Lock, KeyRound } from "lucide-react";
import api from "../services/api";
import { useAuth } from "../context/AuthContext";
import { Button, Input, Card } from "./ui";

export default function LockScreen({ onUnlock }) {
  const { user, logout } = useAuth();
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [checking, setChecking] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setError("");
    setChecking(true);
    try {
      await api.post("/auth/verify-password", { password });
      setPassword("");
      onUnlock();
    } catch (err) {
      setError(err.response?.data?.message || "Incorrect password.");
    } finally {
      setChecking(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100] bg-teal-700/97 backdrop-blur-sm flex items-center justify-center px-4">
      <div className="w-full max-w-sm text-center">
        <div className="mx-auto mb-4 h-14 w-14 rounded-2xl bg-white/10 flex items-center justify-center">
          <Lock size={26} className="text-white" />
        </div>
        <p className="font-display text-2xl text-white">Screen locked</p>
        <p className="text-teal-200 text-sm mt-1 mb-6">
          {user?.full_name} — locked after 30 minutes without activity, to keep patient data safe on this device.
        </p>

        <Card className="text-left">
          <form onSubmit={submit} className="space-y-4">
            <div className="relative">
              <KeyRound size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
              <Input
                type="password" autoFocus className="pl-9"
                placeholder="Enter your password to continue"
                value={password} onChange={(e) => setPassword(e.target.value)}
              />
            </div>
            {error && <p role="alert" className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">{error}</p>}
            <Button type="submit" className="w-full" disabled={checking}>
              {checking ? "Checking…" : "Unlock"}
            </Button>
            <button type="button" onClick={logout} className="w-full text-center text-xs text-ink/40 hover:text-ink/60">
              Sign out instead
            </button>
          </form>
        </Card>
      </div>
    </div>
  );
}
