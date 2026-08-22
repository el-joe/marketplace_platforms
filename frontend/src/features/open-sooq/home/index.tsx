import React from "react";
import HeroBanner from "./hero-banner";
import CategoriesGrid from "./categories-grid";
import HomeBannerTwo from "./home-banner-two";
import { getTranslations } from "next-intl/server";
import HomeBannerThree from "./home-banner-three";
import CarouselListings from "@/src/components/shared/carousel-listing";

export default async function Home() {
  const t = await getTranslations("openSooq.home");
  return (
    <>
      <HeroBanner />
      <CategoriesGrid />
      <HomeBannerTwo />
      <CarouselListings title={t("recommendedForYou")} />
      <CarouselListings title={"Home & Garden"} />
      <CarouselListings title={"Sports & Fitness"} />
      <CarouselListings title={"Food"} />
      <CarouselListings title={"Leisure & Collectibles"} />
      <CarouselListings title={"Businesses & Equipment"} />
      <HomeBannerThree />
    </>
  );
}
