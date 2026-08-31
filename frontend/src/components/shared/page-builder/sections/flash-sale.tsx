"use client";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { cn } from "@/src/lib/utils";

import useLocale from "@/src/hooks/use-locale";
import Price from "@/src/components/shared/Price";

import AddToCartButton from "@/src/components/shared/add-to-cart-button";
import { Block } from "../types";
import { Product } from "@/types/globals";
import SectionTitle from "./section-title";
import { chunks } from "../helpers/chunks-arr";

export const FlashSale = ({ data }: { data: Block }) => {
  const chunksRows = chunks(data?.products || [], 2);
  return (
    <div className="px-4">
      {data?.config?.title_en && (
        <SectionTitle title={data?.config?.title_en} />
      )}
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={Number(data?.config?.items_per_row) / 3 || "auto"}
        spaceBetween={16}
        breakpoints={{
          520: {
            slidesPerView: Number(data?.config?.items_per_row) / 2 || "auto",
          },
          768: {
            slidesPerView: Number(data?.config?.items_per_row) / 1.3 || "auto",
          },
          1024: {
            slidesPerView: Number(data?.config?.items_per_row) || "auto",
          },
        }}
      >
        {chunksRows.map((row, rowIndex) => (
          <SwiperSlide key={rowIndex} className="h-auto! flex! flex-col! gap-4">
            {row.map((p) => (
              <Link
                href={`products/${p?.url_param}`}
                key={p.listing_id}
                className={cn(
                  "block relative",
                  row.length === Number(data?.config?.rows) && "flex-1",
                )}
              >
                <FlashSaleCard p={p} />
              </Link>
            ))}
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

const FlashSaleCard = ({ p }: { p: Product }) => {
  const locale = useLocale();
  return (
    <div className="rounded-lg overflow-hidden">
      <div className="flex gap-2 items-center p-3 bg-white">
        <div className="w-35 rounded-lg bg-gray-2 relative overflow-hidden">
          <div className="bg-purple-800 rounded-ee-lg px-2 py-1 text-main font-bold line-clamp-1 text-xs w-fit">
            {p?.category_name?.[locale]}
          </div>
          <Image
            src={p?.thumbnail}
            alt={p.name_en}
            width={140}
            height={110}
            className="h-27.5 object-contain"
          />
          {/* cart button */}
          <AddToCartButton listingId={p?.listing_id} size="sm" />
        </div>
        <div className="flex-1 flex flex-col justify-evenly h-32">
          <h4 className="line-clamp-2">
            {locale === "ar" ? p.name_ar : p.name_en}
          </h4>
          <Price currency={p.currency} currentPrice={p.price / 100} />
        </div>
      </div>
      <div className="text-center bg-black text-white">
        {!!p.shipping_badge
          ? locale === "ar"
            ? p.shipping_badge?.label_ar
            : p.shipping_badge?.label_en
          : p.vendor?.store_name}
      </div>
    </div>
  );
};
