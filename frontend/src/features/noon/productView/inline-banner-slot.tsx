"use client";

import Image from "next/image";
import useLocale from "@/src/hooks/use-locale";
import { apiBaseUrlGlobal } from "@/src/lib/utils";
import { PlacementBanner } from "@/src/types/placement-banner";
import { SponsoredLink } from "@/src/components/shared/sponsored-link";
import resolveCookie from "@/src/helpers/resolveCookie";

interface Props {
  banner: PlacementBanner;
}

export default function InlineBannerSlot({ banner }: Props) {
  const locale = useLocale();
  const isAr = locale === "ar";

  const desktopSrc =
    (isAr && banner.desktop_image_url_ar) || banner.desktop_image_url;
  const mobileSrc =
    (isAr && banner.mobile_image_url_ar) || banner.mobile_image_url;
  const imgSrc = desktopSrc || mobileSrc;

  if (!imgSrc) return null;

  const title = isAr
    ? banner.title_ar || banner.title_en
    : banner.title_en || banner.title_ar;
  const subtitle = isAr
    ? banner.subtitle_ar || banner.subtitle_en
    : banner.subtitle_en || banner.subtitle_ar;
  const cta = isAr
    ? banner.cta_label_ar || banner.cta_label_en
    : banner.cta_label_en || banner.cta_label_ar;

  const trackLegacyClick = async () => {
    if (!banner.id || banner.is_paid) return;
    const country = await resolveCookie("country");
    fetch(`${apiBaseUrlGlobal}/${country}/banners/${banner.id}/click`, {
      method: "POST",
      keepalive: true,
    }).catch(() => {});
  };

  // Paid ads derived from a product carry a square product photo + title, so
  // they are shown as a compact sponsored card rather than a stretched banner.
  const isProductCard = !!banner.is_paid && !!title;

  if (isProductCard) {
    return (
      <SponsoredLink
        href={banner.cta_url}
        isExternal={banner.is_external}
        ad={banner.ad}
        isPaid
        className="rounded-xl border border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm transition"
      >
        <div className="flex items-center gap-3 md:gap-4 p-2.5 md:p-3 pb-6 md:pb-3">
          <div className="relative size-14 md:size-16 shrink-0 rounded-lg bg-[#f7f7fa] overflow-hidden">
            <Image
              src={imgSrc}
              alt={title || ""}
              fill
              sizes="64px"
              className="object-contain p-1"
            />
          </div>
          <div className="min-w-0 flex-1">
            <p className="line-clamp-2 text-sm md:text-base font-semibold text-gray-900">
              {title}
            </p>
            {subtitle && (
              <p className="line-clamp-1 mt-0.5 text-xs md:text-sm text-gray-500">
                {subtitle}
              </p>
            )}
          </div>
          {cta && (
            <span className="hidden sm:inline-block shrink-0 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
              {cta}
            </span>
          )}
        </div>
      </SponsoredLink>
    );
  }

  return (
    <div onClick={trackLegacyClick}>
      <SponsoredLink
        href={banner.cta_url}
        isExternal={banner.is_external}
        ad={banner.ad}
        isPaid={banner.is_paid}
        className="rounded-xl overflow-hidden"
      >
        <picture>
          {mobileSrc && (
            <source media="(max-width: 767px)" srcSet={mobileSrc} />
          )}
          <Image
            src={imgSrc}
            alt={title || ""}
            width={1200}
            height={150}
            className="w-full h-auto object-cover"
          />
        </picture>
      </SponsoredLink>
    </div>
  );
}
