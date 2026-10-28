import Image from "next/image";
import Link from "next/link";
import React from "react";
import { IMarketerBanner } from "./helpers/types";

export default function StripBanners({
  banners,
}: {
  banners: IMarketerBanner[];
}) {
  const items = banners.filter((b) => b.image_desktop || b.image_mobile);
  if (!items.length) return null;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      {items.map((b) => {
        const desktop = b.image_desktop || b.image_mobile!;
        const mobile = b.image_mobile || desktop;
        const picture = (
          <picture className="block h-[300px] w-full">
            <source media="(min-width: 768px)" srcSet={desktop} />
            <source media="(max-width: 767px)" srcSet={mobile} />
            <Image
              src={desktop}
              alt={b.title ?? ""}
              width={840}
              height={300}
              className="h-full w-full rounded-lg object-cover"
            />
          </picture>
        );
        const href = b.link || b.click_url;
        return href ? (
          <Link key={b.id} href={href} className="block">
            {picture}
          </Link>
        ) : (
          <div key={b.id}>{picture}</div>
        );
      })}
    </div>
  );
}
