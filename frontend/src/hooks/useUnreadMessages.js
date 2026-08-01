import { useState, useEffect, useCallback } from "react";
import api from "../services/api";

export function useUnreadMessages() {
  const [unreadCount, setUnreadCount] = useState(0);

  const refresh = useCallback(() => {
    api.get("/referrals/inbox")
      .then((res) => setUnreadCount(res.data.unread_count || 0))
      .catch(() => {});
  }, []);

  useEffect(() => {
    refresh();
    const interval = setInterval(refresh, 20000);
    return () => clearInterval(interval);
  }, [refresh]);

  return { unreadCount, refresh };
}
