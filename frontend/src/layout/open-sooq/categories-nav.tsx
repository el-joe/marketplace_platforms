"use client";
import React, { useRef, useState } from "react";
import { Swiper, SwiperSlide, useSwiper } from "swiper/react";
import { Button } from "@/src/components/ui/button";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "../../../i18n/navigation";

const categories = [
  "electronics",
  "baby",
  "Toys",
  "Men's Fashion",
  "Women's Fashion",
  "Home & Kitchen",
  "Beauty & Fragrance",
  "Beauty & Fragrance2",
  "Beauty & Fragrance3",
  "Beauty & Fragrance4",
  "Beauty & Fragrance5",
  "Beauty & Fragrance6",
  "Beauty & Fragrance7",
  "Beauty & Fragrance8",
  "Beauty & Fragrance9",
  "Beauty & Fragrance10",
];

const CategoriesNav = () => {
  const [hoveredCategory, setHoveredCategory] = useState<null | string>(null);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const t = useTranslations("header.categoriesNav");
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
        {categories.map((category, i) => (
          <SwiperSlide key={i} className="w-fit! h-fit!">
            <Link
              href={"/"}
              className={`py-1 block border-b border-transparent hover:border-black ${hoveredCategory === category ? "border-b-black" : ""}`}
              onMouseEnter={() => {
                timeoutRef.current = setTimeout(() => {
                  setHoveredCategory(category);
                }, 300);
              }}
            >
              {category}
            </Link>
          </SwiperSlide>
        ))}
        <SwiperButtons />
      </Swiper>
      {/* right banner */}
      <div>
        <span className="w-60 h-7 bg-red rounded-2xl block" />
      </div>
      {/* category details box */}
      <div
        className={`fixed top-26 w-screen left-0 z-30 bg-[#0000005b] h-screen  ${hoveredCategory ? "" : "hidden"}`}
      >
        <div
          className="h-[calc(100vh-200px)] max-h-121 overflow-auto bg-white container p-5 z-30"
          onMouseLeave={() => {
            setHoveredCategory(null);
          }}
        >
          <div className="grid grid-cols-4 gap-5 h-full">
            {/* subcategories lists */}
            <div className="col-span-3">
              {/* one level of subcategories */}
              <ul className="flex gap-8">
                <li>
                  <h4 className="font-semibold mb-3">Lorem, ipsum.</h4>
                  {/* tow level of subcategories */}
                  <ul className="text-sm flex flex-col gap-2">
                    <li>Lorem ipsum dolor sit amet consectetur</li>
                    <li>Lorem ipsum dolor sit amet.</li>
                    <li>lorem6</li>
                    <li>Lorem, ipsum dolor.</li>
                    <li>Lorem.</li>
                  </ul>
                </li>
                <li>
                  <h4 className="font-semibold mb-3">Lorem ipsum dolor sit.</h4>
                  <ul className="text-sm flex flex-col gap-2">
                    <li>Lorem, ipsum dolor.</li>
                    <li>Lorem ipsum dolor, sit amet consectetur Natus?</li>
                    <li>Lorem, ipsum.</li>
                    <li>Lorem ipsum dolor sit.</li>
                    <li>Lorem ipsum dolor sit amet consectetur.</li>
                  </ul>
                </li>
                <li>
                  <h4 className="font-semibold mb-3">Lorem, ipsum.</h4>
                  <ul className="text-sm flex flex-col gap-2">
                    <li>Lorem ipsum dolor sit.</li>
                    <li>Lorem ipsum dolor sit amet consectetur.</li>
                    <li>Lorem, ipsum dolor.</li>
                    <li>Lorem ipsum dolor sit amet consectetur adipisicing.</li>
                    <li>Lorem, ipsum dolor.</li>
                  </ul>
                </li>
              </ul>
            </div>
            {/* image banner */}
            <div className="col-start-4 col-end-4 row-span-2 relative">
              <Image
                src="https://f.nooncdn.com/mpcms/EN0001/assets/41b335f9-d8b2-4698-826a-4a874224b74a.png"
                alt=""
                fill
                sizes="100%"
              ></Image>
            </div>
            {/* top brands list */}
            <div className="col-span-3 h-fit mt-auto">
              <h4 className="font-semibold mb-3">{t("topBrands")}</h4>
              <ul className="flex gap-4">
                <li className="flex flex-col items-center">
                  <div className="w-22 h-16 relative border border-border-color">
                    <Image
                      src={
                        "https://f.nooncdn.com/mpcms/EN0001/assets/0d2c2c1b-8277-4602-94bb-e58128b2522a.png"
                      }
                      alt="brand logo"
                      fill
                      sizes="100%"
                    />
                  </div>
                  <p className="text-light font-semibold text-sm">Puma</p>
                </li>
                <li className="flex flex-col items-center">
                  <div className="w-22 h-16 relative border border-border-color">
                    <Image
                      src={
                        "https://f.nooncdn.com/mpcms/EN0001/assets/0d2c2c1b-8277-4602-94bb-e58128b2522a.png"
                      }
                      alt="brand logo"
                      fill
                      sizes="100%"
                    />
                  </div>
                  <p className="text-light font-semibold text-sm">Puma</p>
                </li>
                <li className="flex flex-col items-center">
                  <div className="w-22 h-16 relative border border-border-color">
                    <Image
                      src={
                        "https://f.nooncdn.com/mpcms/EN0001/assets/0d2c2c1b-8277-4602-94bb-e58128b2522a.png"
                      }
                      alt="brand logo"
                      fill
                      sizes="100%"
                    />
                  </div>
                  <p className="text-light font-semibold text-sm">Puma</p>
                </li>
                <li className="flex flex-col items-center">
                  <div className="w-22 h-16 relative border border-border-color">
                    <Image
                      src={
                        "https://f.nooncdn.com/mpcms/EN0001/assets/0d2c2c1b-8277-4602-94bb-e58128b2522a.png"
                      }
                      alt="brand logo"
                      fill
                      sizes="100%"
                    />
                  </div>
                  <p className="text-light font-semibold text-sm">Puma</p>
                </li>
              </ul>
            </div>
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
