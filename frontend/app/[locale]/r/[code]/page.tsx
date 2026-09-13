"use client";

import { useEffect } from "react";
import { useRouter, useParams } from "next/navigation";
import { getOrCreateSessionId } from "@/src/lib/session-id";
import { apiBaseUrlGlobal } from "@/src/lib/utils";

export default function ReferralRedirectPage() {
  const router = useRouter();
  const params = useParams<{ code: string }>();

  useEffect(() => {
    const sessionId = getOrCreateSessionId();

    fetch(`${apiBaseUrlGlobal}/api/r/${params.code}`, {
      headers: {
        Accept: "application/json",
        "X-Session-Id": sessionId,
      },
    })
      .then((res) => res.json())
      .then((data: { destination?: string }) => {
        router.replace(data.destination ?? "/");
      })
      .catch(() => {
        router.replace("/");
      });
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
