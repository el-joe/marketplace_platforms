"use client";

import { useTranslations } from "next-intl";
import { AlertTriangle } from "lucide-react";

export default function MarketersError({ reset }: { reset: () => void }) {
  const t = useTranslations("marketers");
  return (
    <div role="alert" className="container mx-auto flex flex-col items-center gap-3 px-4 py-24 text-center">
      <div className="flex h-14 w-14 items-center justify-center rounded-full bg-gray-2">
        <AlertTriangle className="h-6 w-6 text-gray" />
      </div>
      <h2 className="text-lg font-black text-primary">{t("errorTitle")}</h2>
      <p className="text-sm text-gray">{t("errorDescription")}</p>
      <button
        type="button"
        onClick={reset}
        className="mt-2 rounded-full bg-primary px-6 py-2 text-sm font-bold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      >
        {t("retry")}
      </button>
    </div>
  );
}
