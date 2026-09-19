import { apiPublicBaseUrlGlobal } from "@/src/lib/utils";
import resolveCookie from "@/src/helpers/resolveCookie";
import type { AdPopup } from "./types";

export async function getActivePopup(): Promise<AdPopup | null> {
  const country = await resolveCookie("country");
  if (!country) return null;

  const res = await fetch(`${apiPublicBaseUrlGlobal}/${country}/active-popup`, {
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
