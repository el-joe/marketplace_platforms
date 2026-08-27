"use client";
import React, { useRef, useState } from "react";
import { Swiper, SwiperSlide, useSwiper } from "swiper/react";
import { Button } from "@/src/components/ui/button";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "../../../i18n/navigation";
import { useQuery } from "@tanstack/react-query";
import useLocale from "@/src/hooks/use-locale";
import { Skeleton } from "@/src/components/ui/skeleton";
import { getCategoriesTree } from "@/src/services/get";
import { CategoryNavTree, Type } from "@/types/category-nav-tree.type";

function categoryHref(category: CategoryNavTree): string {
  switch (category.type) {
    case Type.ClassiFied:
      return `/classified/${category.id}`;
    case Type.Travel:
      return category.slug ? `/travel?category=${category.slug}` : "/travel";
    case Type.Product:
    default:
      return `/${category.slug}`;
  }
}

function subCategoryHref(
  child: CategoryNavTree,
  parent?: CategoryNavTree,
): string {
  switch (child.type ?? parent?.type) {
    case Type.ClassiFied:
      return `/classified/${child.id}`;
    case Type.Travel:
      return child.slug ? `/travel?category=${child.slug}` : "/travel";
    case Type.Product:
    default:
      return `/${child.slug}`;
  }
}

const CategoriesNav = () => {
  const [hoveredCategory, setHoveredCategory] = useState<null | string>(null);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const locale = useLocale();
  const t = useTranslations("header.categoriesNav");
  const { data, isLoading } = useQuery({
    queryKey: ["categoriesTree"],
    queryFn: getCategoriesTree,
  });
  return (
    <div
      className="w-full container hidden md:flex items-center gap-3 h-11 relative"
      onMouseLeave={() => {
        if (timeoutRef.current) {
          clearTimeout(timeoutRef.current);
        }
        setHoveredCategory(null);
      }}
    >
      {/* categories slide */}
      <Swiper
        slidesPerView={"auto"}
        freeMode
        className="flex-1 h-full px-4!"
        wrapperClass="items-center"
        spaceBetween={12}
      >
        {isLoading &&
          Array.from({ length: 12 }).map((_, i) => (
            <SwiperSlide key={i} className="w-fit! h-fit! px-2!">
              <Skeleton className="w-40 h-8" />
            </SwiperSlide>
          ))}
        {data?.map((category, i) => (
          <SwiperSlide key={i} className="w-fit! h-fit!">
            <Link
              href={categoryHref(category)}
              className={`py-1 block border-b border-transparent hover:border-black ${hoveredCategory === category.id ? "border-b-black" : ""} ${category.type === Type.ClassiFied ? "text-orange-600" : ""}`}
              onMouseEnter={() => {
                if (!hoveredCategory) {
                  timeoutRef.current = setTimeout(() => {
                    setHoveredCategory(category.id);
                  }, 300);
                } else {
                  setHoveredCategory(category.id);
                }
              }}
            >
              {category.name?.[locale]}
            </Link>
          </SwiperSlide>
        ))}
        <SwiperButtons />
      </Swiper>
      {/* right banner */}
      {/* <div>
        <span className="w-60 h-7 bg-red rounded-2xl block" />
      </div> */}
      {/* category details box */}
      <div
        className={`fixed top-26 w-screen left-0 z-30 bg-[#0000005b] h-screen  ${hoveredCategory ? "" : "hidden"}`}
      >
        <div
          className="h-[calc(100vh-200px)]s max-h-121s overflow-auto bg-white p-5 z-30"
          onMouseLeave={() => {
            setHoveredCategory(null);
          }}
        >
          <div className="grid grid-cols-4 gap-5 h-full">
            {/* subcategories lists */}
            <div className="col-span-3">
              {/* one level of subcategories */}
              <ul className="flex gap-8">
                {data
                  ?.find((category) => category.id === hoveredCategory)
                  ?.children.map((subCategory) => (
                    <li key={subCategory.id}>
                      <Link
                        href={subCategoryHref(
                          subCategory,
                          data?.find((c) => c.id === hoveredCategory),
                        )}
                      >
                        <h4 className="font-semibold mb-3">
                          {subCategory.name[locale]}
                        </h4>
                      </Link>
                      {/* tow level of subcategories */}
                      <ul className="text-sm flex flex-col gap-2">
                        {subCategory.children.map((e) => (
                          <li key={e.id}>
                            <Link href={subCategoryHref(e, subCategory)}>
                              {e.name[locale]}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    </li>
                  ))}
              </ul>
            </div>
            {/* image banner */}
            {data?.find((c) => c.id === hoveredCategory)?.image_url && (
              <div className="col-start-4 col-end-4 row-span-2 relative h-[30vh]">
                <Image
                  src={
                    data?.find((category) => category.id === hoveredCategory)
                      ?.image_url ?? ""
                  }
                  alt=""
                  fill
                  sizes="100%"
                />
              </div>
            )}
            {/* top brands list */}
            {!!data?.find((category) => category.id === hoveredCategory)?.brands
              ?.length && (
              <div className="col-span-3 h-fit mt-auto">
                <h4 className="font-semibold mb-3">{t("topBrands")}</h4>
                <ul className="flex gap-4">
                  {data
                    ?.find((category) => category.id === hoveredCategory)
                    ?.brands?.map((brand) => (
                      <li key={brand.id} className="flex flex-col items-center">
                        <div className="w-22 h-16 relative border border-border-color">
                          <Image
                            src={brand.logo_url ?? ""}
                            alt="brand logo"
                            fill
                            sizes="100%"
                          />
                        </div>
                        <p className="text-light font-semibold text-sm">
                          {brand.name[locale]}
                        </p>
                      </li>
                    ))}
                </ul>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default CategoriesNav;

const SwiperButtons = () => {
  const swiper = useSwiper();

  return (
    <div className="swiper-nav-btns">
      <Button
        className="absolute -left-1 top-0 bg-white z-10 h-full items-center p-0!"
        onClick={() => swiper.slidePrev()}
      >
        <ChevronLeftIcon />
      </Button>
      <Button
        className="absolute -right-1 top-0 bg-white z-10 h-full items-center p-0!"
        onClick={() => swiper.slideNext()}
      >
        <ChevronRightIcon />
      </Button>
    </div>
  );
};
