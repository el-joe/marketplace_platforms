"use client";

import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { useSearchParams } from "next/navigation";

interface Props {
  active: string;
  type?: string;
}

const GENDER_TABS = [
  { label: "men", value: "male" },
  { label: "women", value: "female" },
];

export default function GenderTabs({ active }: Props) {
  const t = useTranslations("marketers");
  const searchParams = useSearchParams();

  return (
    <nav aria-label={t("genderTabsLabel")} className="flex justify-center">
      <ul className="inline-flex gap-1 rounded-full border border-border-color bg-gray-5 p-1">
        {GENDER_TABS.map((tab) => {
          const isActive = tab.value === active;

          const params = new URLSearchParams(searchParams.toString());
          params.set("gender", tab.value);

          return (
            <li key={tab.value}>
              <Link
                href={`?${params.toString()}`}
                aria-current={isActive ? "page" : undefined}
                className={`block min-w-24 rounded-full px-6 py-2 text-center text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                  isActive
                    ? "bg-main text-white shadow"
                    : "text-light hover:bg-white"
                }`}
              >
                {t(tab.label)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
