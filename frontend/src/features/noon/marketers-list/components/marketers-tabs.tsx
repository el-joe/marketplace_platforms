import Link from "next/link";
import { getTranslations } from "next-intl/server";
import { MARKETER_TABS } from "../helpers/constants";

interface Props {
  activeType?: string;
}

export default async function MarketersTabs({ activeType }: Props) {
  const t = await getTranslations("marketers");

  return (
    <div className="inline-flex flex-wrap justify-center gap-1 rounded-2xl border border-border-color bg-gray-5 p-1.5">
      {MARKETER_TABS.map((tab) => {
        const isActive = activeType === tab.value;

        return (
          <Link
            key={tab.labelKey}
            href={tab.value ? `/marketers?type=${tab.value}` : "/marketers"}
            className={`rounded-xl px-5 py-2.5 text-sm font-bold transition-all duration-200 ${
              isActive
                ? "bg-primary text-white shadow-md"
                : "text-light hover:bg-white hover:shadow-sm"
            }`}
          >
            {t(tab.labelKey)}
          </Link>
        );
      })}
    </div>
  );
}
