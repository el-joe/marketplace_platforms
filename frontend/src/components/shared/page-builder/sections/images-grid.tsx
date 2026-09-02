"use client";
import React from "react";
import { Block } from "../types";
import Image from "next/image";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { AdBadge } from "@/src/components/shared/ad-badge";

export default function ImagesGrid({ data }: { data: Block }) {
  const locale = useLocale();
  const isPromoTiles = data?.block_type === "promo_tiles";
  const gridCols = Number(data?.config?.grid_cols) || 2;
  return (
    <div
      className="flex-1 p-3"
      style={{ backgroundColor: data?.background_color as string }}
    >
      {!!data?.config?.title_en && (
        <h3 className="text-2xl mb-4 font-semibold">
          {locale === "ar" ? data?.config?.title_ar : data?.config?.title_en}
        </h3>
      )}
      <div className="flex gap-4 flex-wrap">
        {isPromoTiles
          ? data.tiles?.map((item, i) => (
              <Link
                href={item?.link_url || ""}
                className="relative h-30 lg:h-40 xl:h-50 2xl:h-65 block"
                style={{
                  width:
                    gridCols > 1
                      ? `calc(100%/${gridCols} - (1rem / ${gridCols}))`
                      : "100%",
                }}
                key={i}
              >
                <Image
                  src={item?.image_url || "/images/no-image-available-icon.jpg"}
                  alt=""
                  width={1200}
                  height={800}
                  className={"object-fill h-full"}
                />
                {item?.is_paid && <AdBadge />}
              </Link>
            ))
          : data.items?.map((item, i) => (
              <Link
                href={item?.link_url || ""}
                className="relative h-30 lg:h-40 xl:h-50 2xl:h-65 block"
                style={{
                  width:
                    gridCols > 1
                      ? `calc(100%/${gridCols} - (1rem / ${gridCols}))`
                      : "100%",
                }}
                key={i}
              >
                <Image
                  src={item?.url || "/images/no-image-available-icon.jpg"}
                  alt=""
                  width={1200}
                  height={800}
                  className={"object-fill h-full"}
                />
                {item?.is_paid && <AdBadge />}
              </Link>
            ))}
      </div>
    </div>
  );
}
