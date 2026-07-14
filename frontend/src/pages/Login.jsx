import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { HeartPulse, User, Lock, ArrowRight, Sun, Moon, ShieldCheck, WifiOff, Activity } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";
import { Button, Input, Field, Card } from "../components/ui";

export default function Login() {
  const { login } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await login(username.trim(), password);
      navigate("/dashboard");
    } catch (err) {
      if (!navigator.onLine) {
        setError("You appear to be offline and haven't signed in on this device before. Connect to the internet for your first sign-in.");
      } else {
        setError(err.response?.data?.message || "Incorrect username or password.");
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="min-h-screen relative overflow-hidden bg-gradient-to-br from-teal-700 via-teal-600 to-teal-800 flex items-center justify-center px-4">
      {/* decorative background accents */}
      <div className="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-teal-400/20 blur-3xl" />
      <div className="absolute -bottom-32 -right-16 h-[28rem] w-[28rem] rounded-full bg-clay/10 blur-3xl" />

      <button
        onClick={toggleTheme}
        aria-label="Toggle theme"
        className="absolute top-5 right-5 h-10 w-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-teal-100 transition-colors"
      >
        {theme === "dark" ? <Moon size={18} /> : <Sun size={18} />}
      </button>

      <div className="w-full max-w-md relative">
        <div className="text-center mb-8">
          <div className="mx-auto mb-4 h-14 w-14 rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center">
            <HeartPulse size={28} strokeWidth={2.25} className="text-white" />
          </div>
          <p className="font-display text-4xl text-white tracking-tight">nullcare</p>
          <p className="text-teal-200 text-sm mt-1">Electronic Medical Record — MUST Teaching Hospital</p>
        </div>

        <Card className="shadow-2xl border-white/10 backdrop-blur">
          <form onSubmit={handleSubmit} className="space-y-4" aria-label="Sign in">
            <Field label="Username" required>
              <div className="relative">
                <User size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
                <Input
                  autoFocus
                  autoComplete="username"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="e.g. doctor1"
                  className="pl-9"
                />
              </div>
            </Field>
            <Field label="Password" required>
              <div className="relative">
                <Lock size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink/35 pointer-events-none" />
                <Input
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="pl-9"
                />
              </div>
            </Field>

            {error && (
              <p role="alert" className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2">
                {error}
              </p>
            )}

            <Button type="submit" className="w-full" size="lg" disabled={loading} icon={loading ? undefined : ArrowRight}>
              {loading ? "Signing in…" : "Sign in"}
            </Button>
          </form>
        </Card>

        <div className="flex items-center justify-center gap-4 mt-5 text-teal-200/80 text-xs">
          <span className="flex items-center gap-1.5"><ShieldCheck size={13} /> Role-based access</span>
          <span className="flex items-center gap-1.5"><WifiOff size={13} /> Works offline</span>
          <span className="flex items-center gap-1.5"><Activity size={13} /> Real-time queues</span>
        </div>

        <p className="text-center text-xs text-teal-200/70 mt-4 leading-relaxed">
          Demo accounts (password <span className="mrn-mono">nullcare123</span>): admin, reception1, nurse1,
          doctor1, labtech1, radiologist1, pharmacist1, billing1, dialysis1, records1
        </p>
      </div>
    </div>
  );
}
