"use client";
import React, { useRef, useState, useMemo } from "react";
import { Swiper, SwiperSlide, useSwiper } from "swiper/react";
import { Button } from "@/src/components/ui/button";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "../../../../i18n/navigation";
import { useQuery } from "@tanstack/react-query";
import useLocale from "@/src/hooks/use-locale";
import { Skeleton } from "@/src/components/ui/skeleton";
import { ICategoryNavTree } from "./types";
import { Type } from "./types/category-nav-tree.type";
import { getCategoriesTreeService } from "./api/get";

// ─── href helpers (unchanged) ─────────────────────────────────────────────────

function categoryHref(category: ICategoryNavTree): string {
  switch (category.type) {
    case Type.ClassiFied:
      // Virtual parent node → Open Souq hub; real node → category browse
      return category.id === "virtual-open-souq"
        ? "/open-sooq"
        : `/classified/${category.id}`;
    case Type.Travel:
      // Virtual parent node → Travel hub; real node → travel with category filter
      return category.id === "virtual-travel"
        ? "/travel"
        : category.slug
          ? `/travel?category=${category.slug}`
          : "/travel";
    case Type.Product:
    default:
      return `/${category.slug}`;
  }
}

function subCategoryHref(
  child: ICategoryNavTree,
  parent?: ICategoryNavTree,
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

// ─── Tree builder ─────────────────────────────────────────────────────────────

/**
 * Takes the flat API array (product + classified + travel nodes at root level)
 * and returns a new array where:
 *  - Product nodes pass through unchanged
 *  - All ClassiFied nodes are nested under a single synthetic "Open Souq" parent
 *  - All Travel nodes are nested under a single synthetic "Travel" parent
 *
 * The two virtual parents are appended at the end so product categories come first.
 */
function buildNavTree(
  nodes: ICategoryNavTree[],
  labels: { openSouq: string; travel: string },
): ICategoryNavTree[] {
  const productNodes = nodes.filter((n) => n.type === Type.Product);
  const classifiedNodes = nodes.filter((n) => n.type === Type.ClassiFied);
  const travelNodes = nodes.filter((n) => n.type === Type.Travel);

  const result: ICategoryNavTree[] = [...productNodes];

  if (classifiedNodes.length > 0) {
    result.push({
      id: "virtual-open-souq",
      type: Type.ClassiFied,
      name: { ar: "المتجر المفتوح", en: labels.openSouq },
      slug: "open-sooq",
      parent_id: null,
      children: classifiedNodes,
    });
  }

  if (travelNodes.length > 0) {
    result.push({
      id: "virtual-travel",
      type: Type.Travel,
      name: { ar: "السفر", en: labels.travel },
      slug: "travel",
      parent_id: null,
      children: travelNodes,
    });
  }

  return result;
}

// ─── Component ────────────────────────────────────────────────────────────────

const CategoriesNav = () => {
  const [hoveredCategory, setHoveredCategory] =
    useState<null | ICategoryNavTree>(null);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const locale = useLocale();
  const t = useTranslations("header.categoriesNav");
  const { data, isLoading } = useQuery({
    queryKey: ["categoriesTree"],
    queryFn: getCategoriesTreeService,
  });

  const navTree = useMemo(
    () =>
      data
        ? buildNavTree(data, {
            openSouq: t("openSouq"),
            travel: t("travel"),
          })
        : [],
    [data, t],
  );

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
        {navTree.map((category, i) => (
          <SwiperSlide key={category.id ?? i} className="w-fit! h-fit!">
            <Link
              href={categoryHref(category)}
              className={`py-1 block border-b border-transparent hover:border-black font-semibold ${
                hoveredCategory?.id === category.id ? "border-b-black" : ""
              } ${category.type === Type.ClassiFied ? "text-orange-600" : ""} ${
                category.type === Type.Travel ? "text-teal-600" : ""
              }`}
              onMouseEnter={() => {
                if (!hoveredCategory) {
                  timeoutRef.current = setTimeout(() => {
                    setHoveredCategory(category);
                  }, 300);
                } else {
                  setHoveredCategory(category);
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
      <div>
        <Image
          src={"/images/header-badge.png"}
          alt=""
          width={230}
          height={30}
        />
      </div>

      {/* category details dropdown */}
      <div
        className={`fixed top-26 inset-x-0 left-0 z-30 bg-[#0000005b] h-screen ${
          hoveredCategory && hoveredCategory.children.length ? "" : "hidden"
        }`}
      >
        <div
          className="h-[calc(100vh-200px)]s max-h-121s overflow-auto bg-white p-5 z-30"
          onMouseLeave={() => {
            setHoveredCategory(null);
          }}
        >
          <div className="flex items-stretch justify-between gap-5 h-full">
            {/* subcategories lists */}
            <div className="flex flex-col justify-between h-auto">
              {/* first level of subcategories */}
              <ul
                className={`${
                  !!hoveredCategory?.children.find((ch) => ch.children.length)
                    ? "flex gap-8"
                    : ""
                }`}
              >
                {hoveredCategory?.children.map((subCategory) => (
                  <li key={subCategory.id}>
                    <Link href={subCategoryHref(subCategory, hoveredCategory)}>
                      <h4
                        className={`${
                          subCategory.children.length
                            ? "font-semibold"
                            : "hover:text-blue"
                        } mb-3`}
                      >
                        {subCategory.name[locale]}
                      </h4>
                    </Link>
                    {/* second level of subcategories */}
                    <ul className="text-sm flex flex-col gap-2">
                      {subCategory.children.map((e) => (
                        <li key={e.id} className="hover:text-blue">
                          <Link
                            href={subCategoryHref(e, subCategory)}
                            className="line-clamp-1"
                          >
                            {e.name[locale]}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </li>
                ))}
              </ul>

              {/* top brands — only for product categories */}
              {!!hoveredCategory?.brands?.length && (
                <div className="col-span-3 h-fit mt-auto">
                  <h4 className="font-semibold mb-3">{t("topBrands")}</h4>
                  <ul className="flex gap-4">
                    {hoveredCategory?.brands?.map((brand) => (
                      <li key={brand.id} className="flex flex-col items-center">
                        <Link
                          href={`/brand/${brand.slug}`}
                          className="w-24 h-16 relative border border-border-color"
                        >
                          <Image
                            src={brand.logo_url ?? ""}
                            alt="brand logo"
                            fill
                            sizes="100%"
                          />
                        </Link>
                        <p className="text-light font-semibold text-sm">
                          {brand.name[locale]}
                        </p>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>

            {/* image banner — only for product categories with an image */}
            {hoveredCategory?.image_url && (
              <Link
                href={categoryHref(hoveredCategory)}
                className="relative w-102 h-121.75 rounded-xl overflow-hidden"
              >
                <Image
                  src={hoveredCategory?.image_url ?? ""}
                  alt=""
                  width={408}
                  height={548}
                  className="aspect-159/190 rounded-xl"
                />
              </Link>
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
        className={`${swiper.isBeginning ? "hidden" : ""} absolute -left-1 top-0 bg-white z-10 h-full items-center p-0!`}
        onClick={() => swiper.slidePrev()}
      >
        <ChevronLeftIcon />
      </Button>
      <Button
        className={`${swiper.isEnd ? "hidden" : ""} absolute -right-1 top-0 bg-white z-10 h-full items-center p-0!`}
        onClick={() => swiper.slideNext()}
      >
        <ChevronRightIcon />
      </Button>
    </div>
  );
};
