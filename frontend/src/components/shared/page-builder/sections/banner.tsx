"use client";
import Image from "next/image";
import React from "react";
import { Block } from "../types";
import useLocale from "@/src/hooks/use-locale";
import { SponsoredLink } from "@/src/components/shared/sponsored-link";

export const Banner = ({ data }: { data: Block }) => {
  const locale = useLocale();
  const imageUrl = data.banner?.image_url?.[locale] || data.banner?.image_url?.en;
  const mobileImageUrl =
    data.banner?.mobile_image_url?.[locale] || data.banner?.mobile_image_url?.en;
  // const aspectRatio = data.banner?.aspect_ratio.replace(":", "/") || "auto";
  // const mobileAspectRatio =
  //   data.banner?.mobile_aspect_ratio.replace(":", "/") || "auto";
  return (
    <SponsoredLink
      href={data.banner?.link_url || "#"}
      isExternal={data.banner?.is_external}
      ad={data.banner?.ad}
      isPaid={data.banner?.is_paid}
    >
      <picture>
        <source media="(min-width: 768px)" srcSet={imageUrl || ""} />
        <source media="(max-width: 767px)" srcSet={mobileImageUrl || ""} />
        <Image
          src={imageUrl || mobileImageUrl || "/images/no-image-available-icon.jpg"}
          alt={data?.banner?.alt_text?.[locale] || data?.banner?.alt_text?.en || ""}
          // fill (not cover) so the whole banner stays visible instead of being cropped.
          className={"w-full h-full max-h-100 object-fill responsive-ratio"}
          // style={
          //   {
          //     "--banner-ratio": aspectRatio,
          //     "--banner-mobile-ratio": mobileAspectRatio,
          //   } as React.CSSProperties
          // }
          width={2400}
          height={400}
        />
      </picture>
    </SponsoredLink>
  );
};
