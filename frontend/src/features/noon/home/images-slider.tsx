"use client";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Block } from "./types";
import { cn } from "@/src/lib/utils";
import SectionTitle from "./section-title";
import { AdBadge } from "@/src/components/shared/ad-badge";
import { chunks } from "./helpers/chunks-arr";

const heights = {
  small: "min-h-[80px]",
  medium: "min-h-[140px]",
  large: "min-h-[200px]",
} as const;

const rounded = {
  rounded: "rounded-lg",
  square: "rounded-none",
  circle: "rounded-full",
} as const;

export const ImagesSlider = ({ data }: { data: Block }) => {
  const chunksRows = chunks(data?.items || [], Number(data?.config?.rows) || 1);
  return (
    <div className="container px-0 md:px-4">
      {data?.config?.title_en && (
        <SectionTitle title={data?.config?.title_en} />
      )}
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={Number(data?.config?.columns) / 3 || "auto"}
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
          <SwiperSlide key={rowIndex} className="h-auto! flex! flex-col!">
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
                  src={i.image_url}
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
