import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";
import type { AdPopup } from "./types";

export async function getActivePopup(): Promise<AdPopup | null> {
  const res = await fetch(`${apiPublicBaseUrlGlobal}/active-popup`, {
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    cache: "no-store",
  });

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }

  const json = (await res.json()) as { popup: AdPopup | null };
  return json.popup;
}
