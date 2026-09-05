"use client";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { Block } from "../types";
import useLocale from "@/src/hooks/use-locale";

export const Banner = ({ data }: { data: Block }) => {
  const t = useTranslations();
  const locale = useLocale();
  const imageUrl = data.banner?.image_url?.[locale] || data.banner?.image_url?.en;
  const mobileImageUrl =
    data.banner?.mobile_image_url?.[locale] || data.banner?.mobile_image_url?.en;
  // const aspectRatio = data.banner?.aspect_ratio.replace(":", "/") || "auto";
  // const mobileAspectRatio =
  //   data.banner?.mobile_aspect_ratio.replace(":", "/") || "auto";
  return (
    <Link href={data.banner?.link_url || "#"}>
      <picture>
        <source media="(min-width: 768px)" srcSet={imageUrl || ""} />
        <source media="(max-width: 767px)" srcSet={mobileImageUrl || ""} />
        <Image
          src={imageUrl || mobileImageUrl || "/images/no-image-available-icon.jpg"}
          alt={data?.banner?.alt_text?.[locale] || data?.banner?.alt_text?.en || ""}
          className={"object-cover responsive-ratio h-full max-h-100"}
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
      {/* AD badge */}
      {false && (
        <div className="p-1 text-xs rounded-md text-light bg-white opacity-60 absolute right-3 bottom-3">
          {t("ad")}
        </div>
      )}
    </Link>
  );
};
