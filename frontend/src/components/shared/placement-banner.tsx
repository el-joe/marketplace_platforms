"use client";

import Image from "next/image";
import useLocale from "@/src/hooks/use-locale";
import { PlacementBanner as PlacementBannerType } from "@/src/types/placement-banner";
import { SponsoredLink } from "@/src/components/shared/sponsored-link";
import { cn } from "@/src/lib/utils";

const variantClasses = {
  cart: "lg:rounded-full",
  product: "w-full rounded-lg",
  search: "w-full aspect-[8/1] lg:aspect-[8/1]",
  category: "w-full aspect-[8/1] lg:aspect-[8/1]",
} as const;

type Props = {
  banner: PlacementBannerType | null | undefined;
  variant: "cart" | "product" | "search" | "category";
};

export function PlacementBanner({ banner, variant }: Props) {
  const locale = useLocale();

  if (!banner || !banner.desktop_image_url) return null;

  const isAr = locale === "ar";
  const desktopUrl =
    (isAr && banner.desktop_image_url_ar) || banner.desktop_image_url;
  const mobileUrl =
    (isAr && banner.mobile_image_url_ar) ||
    banner.mobile_image_url ||
    banner.desktop_image_url;

  const title = isAr ? banner.title_ar || banner.title_en : banner.title_en || banner.title_ar;

  return (
    <SponsoredLink
      href={banner.cta_url}
      isExternal={banner.is_external}
      ad={banner.ad}
      isPaid={banner.is_paid}
      className={cn(variantClasses[variant])}
    >
      <picture>
        <source media="(min-width: 768px)" srcSet={desktopUrl || ""} />
        <source media="(max-width: 767px)" srcSet={mobileUrl || ""} />
        <Image
          src={desktopUrl || ""}
          alt={title || ""}
          width={2400}
          height={400}
          className={cn("w-full h-full object-cover", variantClasses[variant])}
        />
      </picture>
    </SponsoredLink>
  );
}
