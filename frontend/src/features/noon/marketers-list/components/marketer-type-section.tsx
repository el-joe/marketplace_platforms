import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { GOLD_BORDER, GOLD_TEXT, TYPE_SECTION_KEYS } from "../helpers/constants";
import { buildListHref } from "../helpers/search-params";
import type { GenderParam, MarketerSection } from "../helpers/types";
import MarketerCarousel from "./marketer-carousel";
import MarketerPortraitCard from "./marketer-portrait-card";

interface Props {
  section: MarketerSection;
  gender: GenderParam;
}

export default async function MarketerTypeSection({ section, gender }: Props) {
  const t = await getTranslations("marketers");
  const key = TYPE_SECTION_KEYS[section.type as keyof typeof TYPE_SECTION_KEYS];
  const title = section.label ?? (key ? t(key) : section.type);

  return (
    <section aria-label={title} className="mt-10">
      <div className="mb-5 flex items-center justify-between gap-4">
        <h2 className="text-xl font-black text-primary sm:text-2xl">{title}</h2>
        <Link
          href={buildListHref({ gender, type: section.type })}
          className={`shrink-0 rounded-full border px-5 py-1.5 text-sm font-bold transition-colors hover:bg-[#c9a24a] hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${GOLD_BORDER} ${GOLD_TEXT}`}
        >
          {t("viewAll")}
        </Link>
      </div>
      <MarketerCarousel label={title} prevLabel={t("prevSlide")} nextLabel={t("nextSlide")}>
        {section.items.map((marketer) => (
          <MarketerPortraitCard key={marketer.id} marketer={marketer} />
        ))}
      </MarketerCarousel>
    </section>
  );
}
