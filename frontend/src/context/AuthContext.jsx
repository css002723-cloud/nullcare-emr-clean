import { createContext, useContext, useState, useEffect, useCallback } from "react";
import api from "../services/api";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const stored = localStorage.getItem("nullcare_user");
    return stored ? JSON.parse(stored) : null;
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("nullcare_token");
    if (!token) {
      setLoading(false);
      return;
    }
    api
      .get("/auth/me")
      .then((res) => {
        // Extract user data from response
        const userData = res.data.user || res.data;
        setUser(userData);
        localStorage.setItem("nullcare_user", JSON.stringify(userData));
      })
      .catch(() => {
        // offline or token invalid — keep cached user if we have one so the app
        // still works offline; only clear if we're online and got a real 401
        if (navigator.onLine) {
          localStorage.removeItem("nullcare_token");
          localStorage.removeItem("nullcare_user");
          setUser(null);
        }
      })
      .finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (username, password) => {
    const res = await api.post("/login", { 
      email: username,  // Send as email field
      password 
    });
    
    // Extract user data from nested structure
    const userData = res.data.user;
    const token = res.data.access_token;
    
    localStorage.setItem("nullcare_token", token);
    localStorage.setItem("nullcare_user", JSON.stringify(userData));
    setUser(userData);
    return userData;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post("/logout");
    } catch (err) {
      console.error("Logout error:", err);
    }
    localStorage.removeItem("nullcare_token");
    localStorage.removeItem("nullcare_user");
    setUser(null);
  }, []);

  const hasRole = useCallback(
    (...roles) => {
      if (!user) return false;
      if (user.role === "admin") return true;
      return roles.includes(user.role);
    },
    [user]
  );

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, hasRole }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
