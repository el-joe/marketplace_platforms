"use client";

import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";

const NAWY_OPTIONS_PATH = "/nawy-options";
const BOLT = "\u26A1";

// Fixed shortcut stacked above LiveStreamButton (bottom-20 / md:bottom-6, ~48px tall).
export default function NawyOptionsButton() {
  const t = useTranslations("nawyOptions");
  const pathname = usePathname();

  if (
    pathname === NAWY_OPTIONS_PATH ||
    pathname.startsWith(`${NAWY_OPTIONS_PATH}/`)
  )
    return null;

  return (
    <Link
      href={NAWY_OPTIONS_PATH}
      aria-label={t("ariaLabel")}
      className="fixed bottom-36 inset-e-4 md:bottom-24 md:inset-e-6 z-40 flex items-center gap-2 px-4 py-3 rounded-full shadow-xl font-semibold text-sm bg-main text-white transition-all hover:scale-105 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-amber-600"
    >
      <span aria-hidden="true" className="text-base">
        {BOLT}
      </span>
      <span>{t("label")}</span>
    </Link>
  );
}
