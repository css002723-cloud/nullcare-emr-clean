import { WifiOff, RefreshCw, CheckCircle2 } from "lucide-react";
import { useNetworkStatus } from "../hooks/useNetworkStatus";
import { Button } from "./ui";

export default function SyncStatusBar() {
  const { isOnline, syncing, pendingCount, lastSyncMessage, syncNow } = useNetworkStatus();

  if (isOnline && pendingCount === 0 && !lastSyncMessage) return null;

  return (
    <div
      role="status"
      className={`text-xs md:text-sm px-4 md:px-8 py-2 flex items-center gap-3 flex-wrap ${
        !isOnline
          ? "bg-clay/15 text-clay dark:bg-clay/10"
          : "bg-moss/10 text-moss dark:bg-moss/10"
      }`}
    >
      {!isOnline ? (
        <>
          <WifiOff size={15} strokeWidth={2.25} />
          <span className="font-medium">
            You're working offline. Everything you enter is saved on this device and will sync automatically once you're back online.
          </span>
        </>
      ) : (
        <>
          {syncing ? <RefreshCw size={15} strokeWidth={2.25} className="animate-spin" /> : <CheckCircle2 size={15} strokeWidth={2.25} />}
          {pendingCount > 0 && (
            <span className="font-medium">
              {pendingCount} record{pendingCount === 1 ? "" : "s"} waiting to sync.
            </span>
          )}
          {lastSyncMessage && <span className="font-medium">{lastSyncMessage}</span>}
        </>
      )}
      {isOnline && pendingCount > 0 && (
        <Button variant="ghost" size="sm" onClick={syncNow} disabled={syncing} icon={RefreshCw}>
          {syncing ? "Syncing…" : "Sync now"}
        </Button>
      )}
    </div>
  );
}
