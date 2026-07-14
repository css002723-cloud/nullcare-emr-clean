import api from "../services/api";
import { db, getPendingWrites, markSynced, markError, countPending } from "./db";

let listeners = [];
let syncing = false;

export function onSyncEvent(cb) {
  listeners.push(cb);
  return () => {
    listeners = listeners.filter((l) => l !== cb);
  };
}

function notify(event) {
  listeners.forEach((cb) => cb(event));
}

export async function isBackendReachable() {
  if (!navigator.onLine) return false;
  try {
    await api.get("/sync/status", { timeout: 3000 });
    return true;
  } catch {
    return false;
  }
}

/**
 * Pushes all pending offline writes to the backend in one batch.
 * Called automatically when the browser fires 'online', and can also be triggered manually
 * (e.g. a "Sync now" button) or polled periodically in case the 'online' event is unreliable
 * on the device.
 */
export async function syncPendingWrites() {
  if (syncing) return { synced: 0, skipped: true };
  const reachable = await isBackendReachable();
  if (!reachable) return { synced: 0, offline: true };

  syncing = true;
  notify({ type: "sync_start" });
  try {
    const pending = await getPendingWrites();
    if (pending.length === 0) {
      notify({ type: "sync_complete", synced: 0 });
      return { synced: 0 };
    }

    const items = pending.map((p) => ({
      resource_type: p.resource_type,
      client_uuid: p.client_uuid,
      payload: p.payload,
    }));

    const res = await api.post("/sync/push", { items });
    const results = res.data.results;

    let syncedCount = 0;
    for (let i = 0; i < pending.length; i++) {
      const result = results[i];
      if (result.status === "synced" || result.status === "already_synced") {
        await markSynced(pending[i].id);
        syncedCount++;
      } else {
        await markError(pending[i].id, result.message || "sync failed");
      }
    }

    notify({ type: "sync_complete", synced: syncedCount, total: pending.length });
    return { synced: syncedCount, total: pending.length };
  } catch (err) {
    notify({ type: "sync_error", error: err.message });
    return { synced: 0, error: err.message };
  } finally {
    syncing = false;
  }
}

export function initAutoSync() {
  window.addEventListener("online", () => {
    notify({ type: "online" });
    syncPendingWrites();
  });
  window.addEventListener("offline", () => {
    notify({ type: "offline" });
  });

  // Periodic retry in case the 'online' event doesn't fire reliably (common on mobile)
  setInterval(() => {
    if (navigator.onLine) syncPendingWrites();
  }, 30000);

  // Attempt an initial sync on load in case there's a leftover queue from a previous session
  if (navigator.onLine) syncPendingWrites();
}

export { countPending };
