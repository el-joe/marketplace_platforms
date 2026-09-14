"use client";

import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { apiBaseUrlGlobal } from "@/src/lib/utils";
import { PlacementBanner } from "@/src/types/placement-banner";
import resolveCookie from "@/src/helpers/resolveCookie";

interface Props {
  banner: PlacementBanner;
}

export default function InlineBannerSlot({ banner }: Props) {
  const locale = useLocale();
  const t = useTranslations();

  const desktopSrc =
    (locale === "ar" && banner.desktop_image_url_ar) ||
    banner.desktop_image_url;
  const mobileSrc =
    (locale === "ar" && banner.mobile_image_url_ar) || banner.mobile_image_url;
  const imgSrc = desktopSrc || mobileSrc;

  if (!imgSrc) return null;

  const title =
    locale === "ar"
      ? (banner.title_ar ?? banner.title_en)
      : (banner.title_en ?? banner.title_ar);

  const handleClick = async () => {
    if (!banner.id) return;
    const country = await resolveCookie("country");
    fetch(`${apiBaseUrlGlobal}/${country}/banners/${banner.id}/click`, {
      method: "POST",
      keepalive: true,
    }).catch(() => {});
  };

  return (
    <div className="relative my-6 rounded-xl overflow-hidden">
      <Link
        href={banner.cta_url || "#"}
        onClick={handleClick}
        target={banner.is_external ? "_blank" : undefined}
      >
        <picture>
          {mobileSrc && (
            <source media="(max-width: 767px)" srcSet={mobileSrc} />
          )}
          <Image
            src={imgSrc}
            alt={title || ""}
            width={1280}
            height={380}
            className="w-full h-auto object-cover"
          />
        </picture>
      </Link>
      <span className="p-1 text-xs rounded-md text-light bg-white opacity-60 absolute right-3 bottom-3">
        {t("ad")}
      </span>
    </div>
  );
}
