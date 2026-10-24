import { getTranslations } from "next-intl/server";
import { ArrowLeft } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { TYPE_SECTION_KEYS } from "../helpers/constants";
import { buildListHref } from "../helpers/search-params";
import type { GenderParam, MarketersPage, MarketerType } from "../helpers/types";
import MarketerPortraitCard from "./marketer-portrait-card";
import MarketersEmpty from "./marketers-empty";
import MarketersPagination from "./marketers-pagination";

interface Props {
  data: MarketersPage;
  gender: GenderParam;
  type: MarketerType;
}

export default async function MarketersGridView({ data, gender, type }: Props) {
  const t = await getTranslations("marketers");
  const title = t(TYPE_SECTION_KEYS[type]);

  return (
    <section aria-label={title} className="mt-8">
      <div className="mb-6 flex items-center justify-between gap-4">
        <h2 className="text-xl font-black text-primary sm:text-2xl">{title}</h2>
        <Link
          href={buildListHref({ gender })}
          className="inline-flex items-center gap-1.5 text-sm font-bold text-light hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
          {t("backToAll")}
        </Link>
      </div>
      {data.items.length === 0 ? (
        <MarketersEmpty />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            {data.items.map((marketer) => (
              <MarketerPortraitCard key={marketer.id} marketer={marketer} />
            ))}
          </div>
          <MarketersPagination meta={data.meta} gender={gender} type={type} />
        </>
      )}
    </section>
  );
}
