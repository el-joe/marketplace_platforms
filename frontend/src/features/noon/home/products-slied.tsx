"use client";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Grid } from "swiper/modules";
import "swiper/css/grid";

import ProductCard from "@/src/components/shared/ProductCard";
import SpecialProductCard from "@/src/components/shared/SpecialProductCard";
import { Block, Product } from "./types";
import SectionTitle from "./section-title";
import useLocale from "@/src/hooks/use-locale";

export const ProductsSlider = ({ data }: { data: Block }) => {
  const locale = useLocale();

  const rowsCount = data.rows_count ?? 1;
  const cardStyle = data.card_style ?? "normal";
  const itemsPerRow =
    parseInt(String(data.config?.items_per_row ?? "4"), 10) || 4;

  const isSpecial = cardStyle === "special";
  const isMultiRow = rowsCount > 1;

  return (
    <div className="container py-6">
      <SectionTitle
        title={
          locale === "ar"
            ? data.config?.title_ar || ""
            : data.config?.title_en || ""
        }
        showVewAllButton={!!data.config?.show_view_all || false}
      />
      <Swiper
        modules={isMultiRow ? [Navigation, Grid] : [Navigation]}
        navigation
        slidesPerView={isSpecial ? itemsPerRow : "auto"}
        spaceBetween={isSpecial ? 12 : 8}
        {...(isMultiRow
          ? { grid: { rows: rowsCount, fill: "row" as const } }
          : {})}
        breakpoints={
          isSpecial
            ? {
                0: { slidesPerView: 2, spaceBetween: 8 },
                640: { slidesPerView: 3, spaceBetween: 10 },
                1024: { slidesPerView: itemsPerRow, spaceBetween: 12 },
              }
            : {
                768: { spaceBetween: 12 },
                1024: { spaceBetween: 18 },
              }
        }
        className={isSpecial ? "!pb-2" : ""}
      >
        {data?.products?.map((product: Product) => (
          <SwiperSlide
            key={product.listing_id}
            className={isSpecial ? "h-auto!" : "w-fit! h-auto!"}
          >
            {isSpecial ? (
              <SpecialProductCard productData={product} />
            ) : (
              <ProductCard productData={product} />
            )}
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};
