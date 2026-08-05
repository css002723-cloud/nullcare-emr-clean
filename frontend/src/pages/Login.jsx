import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { ArrowRight, Eye, EyeOff } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";
import { Button, Input } from "../components/ui";
import "./Login.css";

export default function Login() {
  const { login } = useAuth();
  const { theme, toggleTheme } = useTheme();
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false); // State for password visibility
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
    <div className="split-screen">
      {/* Left Column */}
      <div className="brand-side">
        <h1 className="logo">Welcome</h1>
        <p>Use your credentials to securely access your clinical health system records.</p>
      </div>

      {/* Right Column */}
      <div className="form-side">
        
        {/* Centered Main Form Area */}
        <div className="form-container-wrapper">
          <div className="form-header">
            <img src="/nullcare.png" alt="NullCare Platform Logo" className="form-logo" />
            <p>Welcome back! Please enter your details.</p>
          </div>

          <form onSubmit={handleSubmit}>
            <Input
              autoFocus
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Enter username" 
            />
            
            {/* Password Wrapper for Positioning Toggle Button */}
            <div className="password-wrapper">
              <Input
                type={showPassword ? "text" : "password"}
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••" 
                required 
              />
              <button
                type="button"
                className="password-toggle-btn"
                onClick={() => setShowPassword(!showPassword)}
                aria-label={showPassword ? "Hide password" : "Show password"}
              >
                {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
              </button>
            </div>

            {error && (
              <p role="alert" className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2 mb-4">
                {error}
              </p>
            )}

            <Button type="submit" className="w-full" size="lg" disabled={loading} icon={loading ? undefined : ArrowRight}>
              {loading ? "Signing in…" : "Sign in"}
            </Button>
          </form>
        </div>

        {/* Bottom Docked Footer */}
        <footer className="form-side-footer">
          <span>Powered by</span>
          <img src="/emr.png" alt="EMR Core System" className="footer-logo" />
        </footer>

      </div>
    </div>
  );
}