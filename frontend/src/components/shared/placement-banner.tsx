"use client";

import Image from "next/image";
import useLocale from "@/src/hooks/use-locale";
import { PlacementBanner as PlacementBannerType } from "@/src/types/placement-banner";
import { SponsoredLink } from "@/src/components/shared/sponsored-link";
import { cn } from "@/src/lib/utils";
import {
  deviceVisibilityClass,
  placementBannerImages,
} from "@/src/helpers/placement-banner-images";

const variantClasses = {
  cart: "lg:rounded-xl h-[42px]",
  cartEmpty: "w-full h-auto",
  checkout: "lg:rounded-full",
  product: "w-full rounded-lg",
  search: "w-full aspect-[8/1] lg:aspect-[8/1]",
  category: "w-full aspect-[8/1] lg:aspect-[8/1]",
} as const;

type Variant = keyof typeof variantClasses;

type Props = {
  banner: PlacementBannerType | null | undefined;
  variant: Variant;
  /** Link used when the banner has no CTA URL, so the banner never links to "#". */
  fallbackHref?: string;
};

export function PlacementBanner({ banner, variant, fallbackHref }: Props) {
  if (!banner) return null;

  return (
    <>
      <PlacementBannerItem
        banner={banner}
        variant={variant}
        fallbackHref={fallbackHref}
      />
      {banner.mobile_variant && (
        <PlacementBannerItem
          banner={banner.mobile_variant}
          variant={variant}
          fallbackHref={fallbackHref}
        />
      )}
    </>
  );
}

function PlacementBannerItem({
  banner,
  variant,
  fallbackHref,
}: Props & { banner: PlacementBannerType }) {
  const locale = useLocale();
  const images = placementBannerImages(banner, locale);
  const visibility = deviceVisibilityClass(images);

  if (visibility === null) return null;

  const title =
    locale === "ar"
      ? banner.title_ar || banner.title_en
      : banner.title_en || banner.title_ar;
  const imageClass = cn(
    "w-full h-full object-fill",
    variantClasses[variant],
  );

  return (
    <SponsoredLink
      href={banner.cta_url || fallbackHref}
      isExternal={banner.is_external}
      ad={banner.ad}
      isPaid={banner.is_paid}
      className={cn(variantClasses[variant], visibility)}
    >
      {images.desktop && (
        <Image
          src={images.desktop}
          alt={title || ""}
          width={2400}
          height={400}
          className={cn(imageClass, images.mobile ? "hidden md:block" : "block")}
        />
      )}
      {images.mobile && (
        <Image
          src={images.mobile}
          alt={title || ""}
          width={750}
          height={200}
          className={cn(imageClass, images.desktop ? "md:hidden" : "block")}
        />
      )}
    </SponsoredLink>
  );
}
