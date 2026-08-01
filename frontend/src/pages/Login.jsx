import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { ArrowRight, Eye, EyeOff } from "lucide-react";
import { useAuth } from "../context/AuthContext";
import { useTheme } from "../context/ThemeContext";
import { Button, Input } from "../components/ui";

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
   <>
   <style>
    {`
    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Poppins", sans-serif;
  background: #fff;
  min-height: 100vh;
}

.split-screen {
  display: flex;
  min-height: 100vh;
  width: 100%;
}

/* Left Column Styling */
.brand-side {
  flex: 1.2;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-start;
  padding: 5rem;
  background-image: linear-gradient(rgba(15, 23, 42, 0.45), rgba(15, 23, 42, 0.85)), url('nullcare.jpg');
  background-size: cover;
  background-position: center;
  color: #fff;
}

.brand-side .logo {
  font-size: 3.8rem;
  font-weight: 700;
  margin-bottom: 1.2rem;
  letter-spacing: -1px;
}

.brand-side p {
  font-size: 1.35rem;
  font-weight: 300;
  max-width: 480px;
  line-height: 1.6;
  opacity: 0.95;
}

/* Right Column Architecture */
.form-side {
  flex: 1;
  display: flex;
  flex-direction: column;
  background-color: #ffffff;
  padding: 2.5rem;
}

/* Wrapper to hold and center everything except the footer */
.form-container-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  width: 100%;
}

.form-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.form-header .form-logo {
  height: 54px;
  width: auto;
  object-fit: contain;
  margin-bottom: 0.75rem;
}

.form-header p {
  font-size: 0.95rem;
  color: #64748b;
  font-weight: 400;
}

form {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 380px;
}

form input {
  outline: none;
  padding: 0.85rem 1.1rem;
  margin-bottom: 1rem;
  font-size: 1rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.5rem;
  transition: all 0.2s ease;
  width: 100%;
}

form input:focus {
  border-color: #1877f2;
  box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.15);
}

/* Password Input Context Container */
.password-wrapper {
  position: relative;
  width: 100%;
}

/* Make space inside the input for the eye icon overlay */
.password-wrapper input {
  padding-right: 3rem; 
}

.password-toggle-btn {
  position: absolute;
  right: 1rem;
  top: 38%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.25rem;
  border-radius: 0.25rem;
  transition: color 0.2s ease;
}

.password-toggle-btn:hover {
  color: #64748b;
}

/* Unique Identity Footer */
.form-side-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding-top: 1.5rem;
  border-top: 1px solid #f1f5f9;
  color: #94a3b8;
  font-size: 0.85rem;
}

.form-side-footer .footer-logo {
  height: 18px;
  width: auto;
  object-fit: contain;
  opacity: 0.8;
}

/* Mobile Screens */
@media (max-width: 768px) {
  .split-screen {
    flex-direction: column;
  }
  
  .brand-side {
    flex: none;
    min-height: 35vh;
    padding: 3rem 2rem;
    align-items: center;
    text-align: center;
  }
  
  .brand-side .logo { font-size: 2.8rem; }
  .brand-side p { font-size: 1.1rem; }

  .form-side {
    flex: 1;
    padding: 3rem 1.5rem 1.5rem 1.5rem;
  }
  
  .form-container-wrapper {
    justify-content: flex-start;
    padding-top: 1rem;
  }
}
    `}
   </style>

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
            <img src="nullcare.png" alt="NullCare Platform Logo" className="form-logo" />
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
          <img src="emr.png" alt="EMR Core System" className="footer-logo" />
        </footer>

      </div>
    </div>
  </>
  );
}