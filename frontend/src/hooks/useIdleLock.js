import { useState, useEffect, useRef, useCallback } from "react";

const IDLE_TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes
const ACTIVITY_EVENTS = ["mousedown", "mousemove", "keydown", "scroll", "touchstart", "click"];

export function useIdleLock(enabled) {
  const [locked, setLocked] = useState(false);
  const timerRef = useRef(null);

  const resetTimer = useCallback(() => {
    if (timerRef.current) clearTimeout(timerRef.current);
    if (!enabled) return;
    timerRef.current = setTimeout(() => setLocked(true), IDLE_TIMEOUT_MS);
  }, [enabled]);

  useEffect(() => {
    if (!enabled) {
      if (timerRef.current) clearTimeout(timerRef.current);
      return;
    }
    resetTimer();
    const handleActivity = () => {
      // Once locked, activity alone shouldn't silently unlock the screen — only a
      // successful password re-entry should. The timer simply doesn't need resetting
      // while locked, since the overlay is already blocking the view.
      if (!locked) resetTimer();
    };
    ACTIVITY_EVENTS.forEach((evt) => window.addEventListener(evt, handleActivity));
    return () => {
      ACTIVITY_EVENTS.forEach((evt) => window.removeEventListener(evt, handleActivity));
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [enabled, locked, resetTimer]);

  const unlock = useCallback(() => {
    setLocked(false);
    resetTimer();
  }, [resetTimer]);

  return { locked, unlock };
}
