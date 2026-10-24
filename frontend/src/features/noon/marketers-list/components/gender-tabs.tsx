import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { GENDER_TABS, GOLD_BG } from "../helpers/constants";
import { buildListHref } from "../helpers/search-params";
import type { GenderParam } from "../helpers/types";

interface Props {
  active: GenderParam;
  type?: string;
}

export default async function GenderTabs({ active, type }: Props) {
  const t = await getTranslations("marketers");

  return (
    <nav aria-label={t("genderTabsLabel")} className="flex justify-center">
      <ul className="inline-flex gap-1 rounded-full border border-border-color bg-gray-5 p-1">
        {GENDER_TABS.map((tab) => {
          const isActive = tab.value === active;
          return (
            <li key={tab.value}>
              <Link
                href={buildListHref({ gender: tab.value, type })}
                aria-current={isActive ? "page" : undefined}
                className={`block min-w-24 rounded-full px-6 py-2 text-center text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                  isActive ? `${GOLD_BG} text-white shadow` : "text-light hover:bg-white"
                }`}
              >
                {t(tab.labelKey)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
