const SESSION_ID_KEY = "_mkt_sid";

/**
 * Returns a stable session ID that persists across page loads.
 * Used for last-click attribution — sent as X-Session-Id header on every request.
 * Falls back gracefully when localStorage is unavailable (SSR).
 */
export function getOrCreateSessionId(): string {
  if (typeof window === "undefined") return "";

  try {
    let id = localStorage.getItem(SESSION_ID_KEY);
    if (!id) {
      id = crypto.randomUUID();
      localStorage.setItem(SESSION_ID_KEY, id);
    }
    return id;
  } catch {
    return crypto.randomUUID();
  }
}
