import { apiBaseUrlGlobal } from "@/src/lib/utils";

/** Fire-and-forget `POST banners/{id}/click` using the contract's `click_url` (absolute or API-relative). */
export function trackBannerClick(clickUrl: string | null | undefined): void {
  if (!clickUrl) return;
  try {
    const url = /^https?:\/\//.test(clickUrl)
      ? clickUrl
      : `${new URL(apiBaseUrlGlobal).origin}/${clickUrl.replace(/^\//, "")}`;
    fetch(url, { method: "POST", keepalive: true }).catch(() => {});
  } catch {
    /* tracking must never break navigation */
  }
}
