import { Suspense } from "react";
import { getClassifiedSectionsService } from "@/src/features/classified/classifiedList/api/get";
import { CLASSIFIED_PLACEMENT } from "@/src/features/classified/classifiedList/helpers/constants";
import SectionBlock from "./components/section-block";
import SectionsSkeleton from "./components/sections-skeleton";
import { getTranslations } from "next-intl/server";

const MAX_SECTIONS = 6;
const ADS_PER_SECTION = 8;

/**
 * Dedicated classified blocks of the home page. Which sections appear, in which order and in which countries is
 * entirely admin configuration (placement "home"); nothing here knows a category by name.
 */
async function Sections() {
  const res = await getClassifiedSectionsService({
    placement: CLASSIFIED_PLACEMENT.home,
    sections: MAX_SECTIONS,
    perSection: ADS_PER_SECTION,
  }).catch(() => null);

  // A section without ads has nothing to show on the home page; its own page still shows the empty state.
  const sections = (res?.data ?? []).filter((s) => s.listings.length > 0);
  if (!sections.length) return null;

  const t = await getTranslations("classified");

  return (
    <div
      className="container flex flex-col gap-8 py-6"
      aria-label={t("sectionsLabel")}
    >
      {sections.map((section) => (
        <SectionBlock key={section.id} section={section} />
      ))}
    </div>
  );
}

export default function ClassifiedSections() {
  return (
    <Suspense fallback={<SectionsSkeleton />}>
      <Sections />
    </Suspense>
  );
}
