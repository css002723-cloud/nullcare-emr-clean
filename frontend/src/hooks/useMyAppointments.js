import { useState, useEffect, useCallback } from "react";
import api from "../services/api";
import { useAuth } from "../context/AuthContext";

/**
 * Only meaningful for a doctor's own schedule, so it's a no-op (count
 * stays 0, no request fires) for every other role — reception/nurse/admin
 * already see the full appointment list front and center, they don't
 * need a nav badge nagging them about it too.
 */
export function useMyAppointments() {
  const { user } = useAuth();
  const [scheduledCount, setScheduledCount] = useState(0);
  const isDoctor = user?.role === "doctor";

  const refresh = useCallback(() => {
    if (!isDoctor) return;
    api.get("/appointments", { params: { doctor_id: user.id, status: "scheduled" } })
      .then((res) => setScheduledCount(Array.isArray(res.data) ? res.data.length : 0))
      .catch(() => {});
  }, [isDoctor, user?.id]);

  useEffect(() => {
    if (!isDoctor) {
      setScheduledCount(0);
      return;
    }
    refresh();
    const interval = setInterval(refresh, 20000);
    return () => clearInterval(interval);
  }, [isDoctor, refresh]);

  return { scheduledCount, refresh };
}
