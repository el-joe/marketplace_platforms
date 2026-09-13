"use client";

import { useEffect } from "react";
import { useParams } from "next/navigation";
import { getOrCreateSessionId } from "@/src/lib/session-id";
import { apiBaseUrlGlobal } from "@/src/lib/utils";

export default function ReferralRedirectPage() {
  const params = useParams<{ code: string }>();

  useEffect(() => {
    const sessionId = getOrCreateSessionId();
    const apiOrigin = new URL(apiBaseUrlGlobal).origin;

    const url = new URL(`${apiOrigin}/api/r/${params.code}`);
    if (sessionId) url.searchParams.set("session_id", sessionId);

    // Plain top-level navigation (not fetch): the browser follows the
    // API's HTTP redirect straight to the destination, no CORS/JSON step.
    window.location.replace(url.toString());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [params.code]);

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="animate-pulse text-sm text-gray-400">
        Redirecting...
      </div>
    </div>
  );
}
