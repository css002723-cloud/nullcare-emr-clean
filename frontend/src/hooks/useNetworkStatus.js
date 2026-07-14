import { useState, useEffect, useCallback } from "react";
import { onSyncEvent, syncPendingWrites, countPending } from "../offline/syncEngine";

export function useNetworkStatus() {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [syncing, setSyncing] = useState(false);
  const [pendingCount, setPendingCount] = useState(0);
  const [lastSyncMessage, setLastSyncMessage] = useState(null);

  const refreshPendingCount = useCallback(() => {
    countPending().then(setPendingCount);
  }, []);

  useEffect(() => {
    refreshPendingCount();
    const unsub = onSyncEvent((event) => {
      if (event.type === "online") setIsOnline(true);
      if (event.type === "offline") setIsOnline(false);
      if (event.type === "sync_start") setSyncing(true);
      if (event.type === "sync_complete") {
        setSyncing(false);
        refreshPendingCount();
        if (event.synced > 0) {
          setLastSyncMessage(`Synced ${event.synced} record${event.synced === 1 ? "" : "s"}`);
          setTimeout(() => setLastSyncMessage(null), 4000);
        }
      }
      if (event.type === "sync_error") setSyncing(false);
    });

    const interval = setInterval(refreshPendingCount, 5000);
    return () => {
      unsub();
      clearInterval(interval);
    };
  }, [refreshPendingCount]);

  const syncNow = useCallback(async () => {
    setSyncing(true);
    await syncPendingWrites();
    refreshPendingCount();
  }, [refreshPendingCount]);

  return { isOnline, syncing, pendingCount, lastSyncMessage, syncNow };
}
