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
  const [showPassword, setShowPassword] = useState(false);
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
        setError(
          "You appear to be offline and haven't signed in on this device before. Connect to the internet for your first sign-in."
        );
      } else {
        setError(
          err.response?.data?.message ||
            "Incorrect username or password."
        );
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-page">

      {/* =====================================================
          LEFT / WELCOME SIDE
          ===================================================== */}
      <div className="welcome-side">
        <div className="welcome-content">
          <h1>Welcome</h1>

          <p>
            Use your credentials to securely access your clinical
            health system records.
          </p>
        </div>
      </div>

      {/* =====================================================
          RIGHT / LOGIN FORM SIDE
          ===================================================== */}
      <div className="form-side">

        {/* Centered Main Form Area */}
        <div className="form-container-wrapper">

          {/* Header */}
          <div className="form-header">

            <img
              src={`${import.meta.env.BASE_URL}nullcare.png`}
              alt="NullCare Platform Logo"
              className="form-logo"
            />

            <p>
              Welcome back! Please enter your details.
            </p>

          </div>

          {/* Login Form */}
          <form onSubmit={handleSubmit}>

            {/* Username */}
            <Input
              autoFocus
              autoComplete="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Enter username"
            />

            {/* Password */}
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
                onClick={() =>
                  setShowPassword((previous) => !previous)
                }
                aria-label={
                  showPassword
                    ? "Hide password"
                    : "Show password"
                }
              >
                {showPassword ? (
                  <EyeOff size={20} />
                ) : (
                  <Eye size={20} />
                )}
              </button>

            </div>

            {/* Error Message */}
            {error && (
              <p
                role="alert"
                className="text-sm text-alert bg-alert/5 border border-alert/20 rounded-lg px-3 py-2 mb-4"
              >
                {error}
              </p>
            )}

            {/* Submit Button */}
            <Button
              type="submit"
              className="w-full"
              size="lg"
              disabled={loading}
              icon={
                loading
                  ? undefined
                  : ArrowRight
              }
            >
              {loading ? "Signing in…" : "Sign in"}
            </Button>

          </form>
        </div>

        {/* =====================================================
            FOOTER
            ===================================================== */}
        <footer className="form-side-footer">

          <span>Powered by</span>

          <img
            src={`${import.meta.env.BASE_URL}emr.png`}
            alt="EMR Core System"
            className="footer-logo"
          />

        </footer>

      </div>
    </div>
  );
}