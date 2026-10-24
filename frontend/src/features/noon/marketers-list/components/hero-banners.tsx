import { getTranslations } from "next-intl/server";
import type { MarketerBanner } from "../helpers/types";
import HeroBannerSlider from "./hero-banner-slider";

export default async function HeroBanners({ banners }: { banners: MarketerBanner[] }) {
  if (banners.length === 0) return null;
  const t = await getTranslations("marketers");

  return (
    <HeroBannerSlider
      banners={banners}
      regionLabel={t("heroLabel")}
      prevLabel={t("prevSlide")}
      nextLabel={t("nextSlide")}
      goToLabel={t("goToSlide", { n: "{n}" })}
    />
  );
}
