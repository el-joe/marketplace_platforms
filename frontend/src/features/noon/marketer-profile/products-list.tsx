"use client";
import React from "react";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";
import { useTranslations } from "next-intl";
import { Navigation } from "swiper/modules";
import { useSearchParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { IProduct } from "@/types";
import ProductCard from "@/src/components/shared/product-card";
import Pagination from "@/src/components/shared/pagination";
import { Meta } from "./helpers/types";

type Props = {
  list: IProduct[];
  listType: string;
  title: string;
  meta: Meta;
};

export default function ProductsList({ list, listType, title, meta }: Props) {
  const t = useTranslations("marketers");
  const searchParams = useSearchParams();
  if (!list.length) {
    return;
  }
  const isTypeSelected = searchParams.has("list-type");

  const linkParams = new URLSearchParams(searchParams.toString());

  if (isTypeSelected) {
    linkParams.delete("list-type");
  } else {
    linkParams.set("list-type", listType);
  }

  const targetHref = `?${linkParams.toString()}`;

  if (isTypeSelected && searchParams.get("list-type") !== listType) {
    return;
  }

  return (
    <div>
      <div className="py-2 flex justify-between items-center">
        <h2 className="text-lg font-bold">{title}</h2>

        <Link href={targetHref} className="flex items-center">
          {isTypeSelected ? (
            <>
              <ArrowLeft className="size-4" /> {t("back")}
            </>
          ) : (
            t("viewAll")
          )}
        </Link>
      </div>

      {isTypeSelected ? (
        <div>
          <div className="flex flex-wrap gap-3 mt-3">
            {list.map((p) => (
              <ProductCard
                key={p.listing_id}
                productData={p}
                className="h-auto w-[calc((100%-12px)/2)] xl:w-[calc((100%-48px)/5)]"
              />
            ))}
          </div>

          <Pagination totalPages={meta.last_page} />
        </div>
      ) : (
        <Swiper
          modules={[Navigation]}
          navigation
          slidesPerView={"auto"}
          spaceBetween={12}
          // breakpoints={{
          //   576: { slidesPerView: 3 },
          //   992: { slidesPerView: 4 },
          //   1200: { slidesPerView: 6 },
          // }}
        >
          {list.map((p) => (
            <SwiperSlide key={p.listing_id} className="w-fit! h-auto!">
              <ProductCard
                key={p.listing_id}
                productData={p}
                // className="h-auto w-[calc((100%-12px)/2)] xl:w-[calc((100%-48px)/5)]"
              />
            </SwiperSlide>
          ))}
        </Swiper>
      )}
    </div>
  );
}
