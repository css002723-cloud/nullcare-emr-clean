import { useState } from "react";
import { KeyRound, ShieldAlert } from "lucide-react";
import api from "../services/api";
import { useAuth } from "../context/AuthContext";
import { Button, Input, Field, Card } from "./ui";

export default function ForcedPasswordChange() {
  const { user, refreshUser, logout } = useAuth();
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState("");
  const [saving, setSaving] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setError("");
    if (newPassword.length < 6) {
      setError("Password must be at least 6 characters.");
      return;
    }
    if (newPassword !== confirmPassword) {
      setError("Passwords don't match.");
      return;
    }
    setSaving(true);
    try {
      await api.post("/auth/change-password-self", { new_password: newPassword });
      await refreshUser();
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't update your password — please try again.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100] bg-teal-700 flex items-center justify-center px-4">
      <div className="w-full">
        <div className="text-center mb-6">
          <div className="mx-auto mb-3 h-12 w-12 rounded-2xl bg-white/10 flex items-center justify-center">
            <ShieldAlert size={24} className="text-white" />
          </div>
          <p className="font-display text-2xl text-white">Password change required</p>
          <p className="text-teal-200 text-sm mt-1">
            {user?.password_expired
              ? "Your password is more than 6 months old and must be changed before you continue."
              : "An administrator requires you to set a new password before continuing."}
          </p>
        </div>

        <Card>
          <form onSubmit={submit} className="space-y-4">
            <Field label="New password" required>
              <div className="relative">
                <KeyRound size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
                <Input
                  type="password" autoFocus className="pl-9"
                  value={newPassword} onChange={(e) => setNewPassword(e.target.value)}
                  autoComplete="new-password"
                />
              </div>
            </Field>
            <Field label="Confirm new password" required>
              <Input
                type="password" value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                autoComplete="new-password"
              />
            </Field>
            {error && <p role="alert" className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">{error}</p>}
            <Button type="submit" className="w-full" disabled={saving}>
              {saving ? "Updating…" : "Set new password"}
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
