"use client";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Grid } from "swiper/modules";
import "swiper/css/grid";

import ProductCard from "@/src/components/shared/product-card";
import useLocale from "@/src/hooks/use-locale";
import { Block } from "../types";
import { chunks } from "../helpers/chunks-arr";
import SectionTitle from "./section-title";
import SpotlightCard from "./spotlight-card";

export const ProductsSlider = ({ data }: { data: Block }) => {
  const locale = useLocale();
  const chunksRows = chunks(
    data?.products || [],
    Number(data?.config?.rows_count) || 1,
  );

  const rowsCount = data.rows_count ?? 1;
  const cardStyle = data?.config?.card_style ?? "normal";
  // const itemsPerRow =
  //   parseInt(String(data.config?.items_per_row ?? "4"), 10) || 4;

  const isSpecial = cardStyle === "special";
  const isMultiRow = rowsCount > 1;

  return (
    <div>
      {(locale === "ar" ? data.config?.title_ar : data.config?.title_en) && (
        <SectionTitle
          title={
            locale === "ar"
              ? data.config?.title_ar || ""
              : data.config?.title_en || ""
          }
          showVewAllButton={!!data.config?.show_view_all || false}
          viewAllUrl={data.config?.view_all_url}
        />
      )}
      <Swiper
        modules={isMultiRow ? [Navigation, Grid] : [Navigation]}
        navigation
        slidesPerView={Number(data.config.items_per_row) / 2.6 || 8}
        spaceBetween={isSpecial ? 12 : 8}
        {...(isMultiRow
          ? { grid: { rows: rowsCount, fill: "row" as const } }
          : {})}
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
        // breakpoints={
        //   isSpecial
        //     ? {
        //         0: { slidesPerView: 2, spaceBetween: 8 },
        //         640: { slidesPerView: 3, spaceBetween: 10 },
        //         1024: { slidesPerView: itemsPerRow, spaceBetween: 12 },
        //       }
        //     : {
        //         768: { spaceBetween: 12 },
        //         1024: { spaceBetween: 18 },
        //       }
        // }
        // className={isSpecial ? "!pb-2" : ""}
      >
        {chunksRows?.map((row, i) => (
          <SwiperSlide
            key={i}
            // className={"h-auto! flex! flex-col! w-fit! gap-4"}
            className={"h-auto! flex! flex-col! gap-4"}
          >
            {row.map((product) =>
              isSpecial ? (
                <SpotlightCard data={product} key={product.listing_id} />
              ) : (
                // <SpecialProductCard productData={product} />
                <ProductCard
                  productData={product}
                  key={product.listing_id}
                  className="w-auto!"
                />
              ),
            )}
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};
