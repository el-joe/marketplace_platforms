import { apiBaseUrl } from "@/src/lib/utils";
import { getOrCreateSessionId } from "@/src/lib/session-id";
import { PaidAdMeta } from "@/src/components/shared/page-builder/types";

const FLUSH_INTERVAL_MS = 5000;
const MAX_QUEUE_SIZE = 20;

let queue: Array<{ id: string; sig: string }> = [];
const seen = new Set<string>();
let listenersAttached = false;

function flush() {
  if (!queue.length) return;
  const items = queue;
  queue = [];

  const sessionId = getOrCreateSessionId();
  const payload = JSON.stringify({ items, session_id: sessionId });
  const url = `${apiBaseUrl}/ads/impressions`;

  if (typeof navigator !== "undefined" && navigator.sendBeacon) {
    const blob = new Blob([payload], { type: "text/plain" });
    const sent = navigator.sendBeacon(url, blob);
    if (sent) return;
  }

  fetch(url, {
    method: "POST",
    keepalive: true,
    headers: {
      "Content-Type": "application/json",
      "X-Session-Id": sessionId,
    },
    body: payload,
  }).catch(() => {});
}

function ensureListeners() {
  if (listenersAttached || typeof window === "undefined") return;
  listenersAttached = true;

  setInterval(flush, FLUSH_INTERVAL_MS);

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "hidden") flush();
  });
  window.addEventListener("pagehide", flush);
}

export function queueImpression(ad: PaidAdMeta) {
  if (!ad?.id || seen.has(ad.id)) return;
  seen.add(ad.id);

  ensureListeners();
  queue.push({ id: ad.id, sig: ad.sig });

  if (queue.length >= MAX_QUEUE_SIZE) flush();
}

export function trackAdClick(ad: PaidAdMeta) {
  if (!ad?.id) return;

  const sessionId = getOrCreateSessionId();
  fetch(`${apiBaseUrl}/ads/clicks`, {
    method: "POST",
    keepalive: true,
    headers: {
      "Content-Type": "application/json",
      "X-Session-Id": sessionId,
    },
    body: JSON.stringify({ id: ad.id, sig: ad.sig, session_id: sessionId }),
  }).catch(() => {});
}
