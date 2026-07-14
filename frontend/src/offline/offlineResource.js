import api from "../services/api";
import { queueWrite, genClientUuid, cacheSet, cacheGet } from "./db";
import { isBackendReachable } from "./syncEngine";

/**
 * Attempts a direct API POST. If offline (or the request fails due to connectivity),
 * queues the write locally with a client_uuid and returns an optimistic local record
 * so the UI can proceed immediately — the record is reconciled with a real server ID
 * once syncPendingWrites() runs.
 *
 * resourceType must match a key in the backend's RESOURCE_MODELS map (see sync.py).
 */
export async function createWithOfflineFallback(resourceType, endpoint, payload) {
  const clientUuid = genClientUuid();
  const payloadWithUuid = { ...payload, client_uuid: clientUuid };

  const online = await isBackendReachable();
  if (online) {
    try {
      const res = await api.post(endpoint, payloadWithUuid);
      return { data: res.data, offline: false };
    } catch (err) {
      if (err.response) {
        // Real server-side error (validation, permission) — don't silently queue, surface it.
        throw err;
      }
      // Network-level failure even though isBackendReachable said online — fall through to queue.
    }
  }

  await queueWrite(resourceType, clientUuid, payload);
  return {
    data: { ...payload, client_uuid: clientUuid, id: null, _pendingSync: true },
    offline: true,
  };
}

/** GET wrapper that caches successful responses and falls back to cache when offline. */
export async function getWithCache(cacheKey, endpoint, params) {
  const online = await isBackendReachable();
  if (online) {
    try {
      const res = await api.get(endpoint, { params });
      await cacheSet(cacheKey, res.data);
      return { data: res.data, fromCache: false };
    } catch (err) {
      const cached = await cacheGet(cacheKey);
      if (cached) return { data: cached, fromCache: true };
      throw err;
    }
  }
  const cached = await cacheGet(cacheKey);
  if (cached) return { data: cached, fromCache: true };
  return { data: [], fromCache: true, empty: true };
}
