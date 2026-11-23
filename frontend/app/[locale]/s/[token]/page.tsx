"use client";

import { useEffect } from "react";
import { useParams } from "next/navigation";
import { getOrCreateSessionId } from "@/src/lib/session-id";
import { apiBaseUrlGlobal } from "@/src/lib/utils";

/**
 * Vendor product share link / QR code (WS12 #24). The API records the visit for
 * this browser's session id (same id checkout sends as X-Session-Id), so a
 * purchase of that vendor's product can earn the vendor a commission discount,
 * then redirects to the product page.
 */
export default function ProductLinkRedirectPage() {
  const params = useParams<{ token: string }>();
  useEffect(() => {
    const channel =
      new URLSearchParams(window.location.search).get("c") === "qr" ? "qr" : "link";
    const sessionId = getOrCreateSessionId();
    const apiOrigin = new URL(apiBaseUrlGlobal).origin;

    const url = new URL(`${apiOrigin}/api/s/${encodeURIComponent(params.token)}`);
    if (sessionId) url.searchParams.set("session_id", sessionId);
    url.searchParams.set("c", channel);

    window.location.replace(url.toString());
  }, [params.token]);

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="animate-pulse text-sm text-gray-400">…</div>
    </div>
  );
}
