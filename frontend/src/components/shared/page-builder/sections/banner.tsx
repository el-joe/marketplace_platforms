import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { Block } from "../types";

export const Banner = ({ data }: { data: Block }) => {
  const t = useTranslations();
  // const aspectRatio = data.banner?.aspect_ratio.replace(":", "/") || "auto";
  // const mobileAspectRatio =
  //   data.banner?.mobile_aspect_ratio.replace(":", "/") || "auto";
  return (
    <Link href={data.banner?.link_url || "#"}>
      <picture>
        <source
          media="(min-width: 768px)"
          srcSet={data.banner?.image_url || ""}
        />
        <source
          media="(max-width: 767px)"
          srcSet={data.banner?.mobile_image_url || ""}
        />
        <Image
          src={
            data.banner?.image_url ||
            data.banner?.mobile_image_url ||
            "/images/no-image-available-icon.jpg"
          }
          alt={data?.banner?.alt_text.en || ""}
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
