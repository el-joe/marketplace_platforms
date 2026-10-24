import { getTranslations } from "next-intl/server";
import { getGroupedMarketers, getMarketersByType } from "./api";
import AdStrip from "./components/ad-strip";
import GenderTabs from "./components/gender-tabs";
import HeroBanners from "./components/hero-banners";
import MarketerTypeSection from "./components/marketer-type-section";
import MarketersEmpty from "./components/marketers-empty";
import MarketersGridView from "./components/marketers-grid-view";
import type { ListSearchParams } from "./helpers/types";

export default async function MarketersListView({ gender, type, page }: ListSearchParams) {
  const t = await getTranslations("marketers");

  // "View all" mode: paginated grid of one type, no hero/strip.
  if (type) {
    const data = await getMarketersByType({ gender, type, page });
    return (
      <div className="container mx-auto px-4 py-6 sm:py-8">
        <h1 className="sr-only">{t("heading")}</h1>
        <GenderTabs active={gender} type={type} />
        <MarketersGridView data={data} gender={gender} type={type} />
      </div>
    );
  }

  const { sections, banners } = await getGroupedMarketers(gender);

  return (
    <div className="container mx-auto px-4 py-6 sm:py-8">
      <h1 className="sr-only">{t("heading")}</h1>
      <HeroBanners banners={banners.hero} />
      <div className="mt-8">
        <GenderTabs active={gender} />
      </div>
      {sections.length === 0 ? (
        <MarketersEmpty />
      ) : (
        sections.map((section) => (
          <MarketerTypeSection key={section.type} section={section} gender={gender} />
        ))
      )}
      <AdStrip banners={banners.strip} />
    </div>
  );
}
