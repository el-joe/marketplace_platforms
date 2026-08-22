"use client";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";

export default function EmptyState() {
  const t = useTranslations("wishlist");
  return (
    <div className="w-full h-full flex flex-col items-center justify-start pt-12 gap-3">
      <video autoPlay loop playsInline muted className="w-87.5">
        <source
          type="video/mp4"
          src="https://f.nooncdn.com/s/app/com/noon/images/wishlist-empty-desktop.mp4"
        />
        <Image
          src={
            "https://f.nooncdn.com/s/app/com/noon/images/wishlist-empty-desktop-fallback.gif"
          }
          alt="empty wishlist"
          width={350}
          height={305}
        />
      </video>
      <p className="text-2xl font-semibold text-light">
        {t("readyToMakeAWish")}
      </p>
      <p className="font-semibold text-gray">
        {t("readyToMakeAWishDescription")}
      </p>
    </div>
  );
}
