/**
 * Offline-first local database.
 *
 * `syncQueue` holds every write made while offline (or optimistically, even while online,
 * to keep behavior consistent). Each entry has:
 *   - resource_type: matches the backend's RESOURCE_MODELS keys (patient, encounter, vitals, note, order, prescription, referral)
 *   - client_uuid: generated locally, used by the backend to dedupe on retried sync
 *   - payload: the fields needed to create the record
 *   - status: 'pending' | 'synced' | 'error'
 *
 * `cache` mirrors GET responses (patients, encounters, worklists) so the UI has something
 * to render immediately when opened offline, before any sync has happened.
 */
import Dexie from "dexie";

export const db = new Dexie("nullcare_offline");

db.version(1).stores({
  syncQueue: "++id, resource_type, client_uuid, status, created_at",
  cache: "key",
});

export async function queueWrite(resourceType, clientUuid, payload) {
  return db.syncQueue.add({
    resource_type: resourceType,
    client_uuid: clientUuid,
    payload,
    status: "pending",
    created_at: new Date().toISOString(),
  });
}

export async function getPendingWrites() {
  return db.syncQueue.where("status").equals("pending").toArray();
}

export async function markSynced(id) {
  return db.syncQueue.update(id, { status: "synced" });
}

export async function markError(id, message) {
  return db.syncQueue.update(id, { status: "error", error_message: message });
}

export async function countPending() {
  return db.syncQueue.where("status").equals("pending").count();
}

export async function cacheSet(key, value) {
  return db.cache.put({ key, value, cached_at: new Date().toISOString() });
}

export async function cacheGet(key) {
  const row = await db.cache.get(key);
  return row ? row.value : null;
}

export function genClientUuid() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}
