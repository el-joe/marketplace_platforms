"use client";
import CarouselProducts from "@/src/components/shared/CarouselProducts";
import { useTranslations } from "next-intl";

export default function RecommendedSection() {
  const t = useTranslations("cart");
  return (
    <div className="rounded-[16px] bg-white max-w-110 lg:max-w-145 xl:max-w-180 hidden md:block">
      <CarouselProducts title={t("recommendedForYou")} />
    </div>
  );
}
