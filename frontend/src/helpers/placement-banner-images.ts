import { PlacementBanner } from "@/src/types/placement-banner";

export type PlacementBannerImages = {
  desktop: string | null;
  mobile: string | null;
};

/**
 * Images of a placement banner for the current locale. The API already resolves the
 * locale × device fallback and device targeting (a device the banner does not target is null),
 * so no cross-device fallback happens here.
 */
export function placementBannerImages(
  banner: PlacementBanner,
  locale: "en" | "ar",
): PlacementBannerImages {
  const isAr = locale === "ar";
  return {
    desktop:
      (isAr ? banner.desktop_image_url_ar : banner.desktop_image_url) ?? null,
    mobile:
      (isAr ? banner.mobile_image_url_ar : banner.mobile_image_url) ?? null,
  };
}

/**
 * Responsive visibility for the banner wrapper. Rendered with CSS breakpoints (md = 768px)
 * instead of user-agent sniffing so the same SSR/CDN-cached HTML is correct on every device.
 * Returns null when the banner has nothing to show on any device.
 */
export function deviceVisibilityClass({
  desktop,
  mobile,
}: PlacementBannerImages): string | null {
  if (desktop && mobile) return "";
  if (desktop) return "hidden md:block";
  if (mobile) return "md:hidden";
  return null;
}

/** True when the banner (or its mobile variant) has an image for at least one device. */
export function hasPlacementBannerImage(
  banner: PlacementBanner | null | undefined,
  locale: "en" | "ar",
): boolean {
  if (!banner) return false;
  const own = deviceVisibilityClass(placementBannerImages(banner, locale));
  return own !== null || hasPlacementBannerImage(banner.mobile_variant, locale);
}
