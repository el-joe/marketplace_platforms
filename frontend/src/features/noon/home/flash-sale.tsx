"use client";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { Block, Product } from "./types";
import { cn } from "@/src/lib/utils";
import SectionTitle from "./section-title";
import { AdBadge } from "@/src/components/shared/ad-badge";
import { chunks } from "./helpers/chunks-arr";
import { Button } from "@/src/components/ui/button";
import { PlusIcon } from "lucide-react";
import useLocale from "@/src/hooks/use-locale";
import Price from "@/src/components/shared/Price";
import { useCartContext } from "@/src/providers/cart-provider";
import { Spinner } from "@/src/components/ui/spinner";

export const FlashSale = ({ data }: { data: Block }) => {
  const chunksRows = chunks(data?.products || [], 2);
  return (
    <div className="container px-0 md:px-4">
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
          <SwiperSlide key={rowIndex} className="h-auto! flex! flex-col!">
            {row.map((p) => (
              <Link
                href={`products/${p?.url_param}`}
                key={p.listing_id}
                className={cn(
                  "block relative mb-2",
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
  const { addItem, isMutating } = useCartContext();
  return (
    <div className="rounded-lg overflow-hidden">
      <div className="flex gap-2 items-center p-3 bg-white">
        <div className="w-35 rounded-lg bg-gray-2 relative overflow-hidden">
          <div className="bg-purple-800 rounded-ee-lg px-2 py-1 text-main font-bold line-clamp-1 text-xs w-fit">
            {p?.vendor?.store_name}
          </div>
          <Image
            src={p?.thumbnail}
            alt={p.name_en}
            width={140}
            height={110}
            className="h-27.5 object-contain"
          />
          {/* cart button */}
          <Button
            variant={"outline"}
            className={
              "absolute bottom-1 lg:bottom-2 right-2 z-10 p-2!  min-w-0! min-h-0! aspect-square"
            }
            disabled={isMutating}
            onClick={(e) => {
              e.preventDefault();
              addItem({ quantity: 1, vendorListingId: p.listing_id });
            }}
          >
            {isMutating ? <Spinner /> : <PlusIcon className={`size-4`} />}
          </Button>
        </div>
        <div className="flex-1">
          <h4 className="line-clamp-2">
            {locale === "ar" ? p.name_ar : p.name_en}
          </h4>
          <Price currency={p.currency} currentPrice={p.price_formatted} />
        </div>
      </div>
      <div className="text-center bg-black text-white">
        {locale === "ar"
          ? p.shipping_badge?.label_ar
          : p.shipping_badge?.label_en}
      </div>
    </div>
  );
};
