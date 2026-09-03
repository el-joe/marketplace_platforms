"use client";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { cn } from "@/src/lib/utils";
import { AdBadge } from "@/src/components/shared/ad-badge";
import { chunks } from "../helpers/chunks-arr";
import { Block } from "../types";
import SectionTitle from "./section-title";

const heights = {
  small: "min-h-[80px] max-h-[140px]",
  medium: "min-h-[140px] max-h-[200px]",
  large: "min-h-[200px] max-h-[280px]",
} as const;

const rounded = {
  rounded: "rounded-lg",
  square: "rounded-none",
  circle: "rounded-full",
} as const;

export const ImagesSlider = ({ data }: { data: Block }) => {
  const chunksRows = chunks(data?.items || [], Number(data?.config?.rows) || 1);
  return (
    <div>
      {(data?.config?.title_en || data?.config?.title_ar) && (
        <SectionTitle
          title={{ en: data?.config?.title_en ?? "", ar: data?.config?.title_ar ?? "" }}
        />
      )}
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={Number(data?.config?.columns) / 3.3 || "auto"}
        spaceBetween={16}
        breakpoints={{
          520: {
            slidesPerView: Number(data?.config?.columns) / 2 || "auto",
          },
          768: {
            slidesPerView: Number(data?.config?.columns) / 1.3 || "auto",
          },
          1024: {
            slidesPerView: Number(data?.config?.columns) || "auto",
          },
        }}
      >
        {chunksRows.map((row, rowIndex) => (
          <SwiperSlide key={rowIndex} className="h-auto! flex! flex-col! gap-4">
            {row.map((i) => (
              <Link
                href={i?.link_url || "#"}
                key={i.id}
                className={cn(
                  "block relative mb-2",
                  row.length === Number(data?.config?.rows) && "flex-1",
                )}
              >
                <Image
                  src={i.image_url || "/images/no-image-available-icon.jpg"}
                  alt="category"
                  width={2400}
                  height={400}
                  className={cn(
                    "object-fill h-full",
                    heights[
                      (data?.config?.size_preset ||
                        "medium") as keyof typeof heights
                    ],
                    rounded[
                      (data?.config?.image_shape ||
                        "rounded") as keyof typeof rounded
                    ],
                  )}
                />
                {i?.is_paid && <AdBadge />}
              </Link>
            ))}
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};
