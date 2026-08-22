"use client";

import React from "react";
import { useTranslations } from "next-intl";
import { AlertCircle, ExternalLink } from "lucide-react";
import { Button } from "@/src/components/ui/button";

interface Props {
  redirectUrl: string;
}

export default function RedirectAlert({ redirectUrl }: Props) {
  const t = useTranslations("checkoutSuccess");

  return (
    <div className="bg-amber-50 border border-amber-200 rounded-2xl p-5 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
      <div className="flex items-start gap-3">
        <div className="p-2 rounded-xl bg-amber-100 text-amber-800 shrink-0">
          <AlertCircle className="size-6" />
        </div>
        <div>
          <h3 className="font-bold text-amber-900 text-base">
            {t("requiresRedirect")}
          </h3>
          <p className="text-sm text-amber-700 mt-1">{t("redirectMessage")}</p>
        </div>
      </div>
      <a
        href={redirectUrl}
        target="_blank"
        rel="noopener noreferrer"
        className="w-full md:w-auto"
      >
        <Button className="w-full md:w-auto bg-amber-600 hover:bg-amber-700 text-white font-bold h-11 px-6 rounded-xl flex items-center justify-center gap-2">
          <span>{t("completePayment")}</span>
          <ExternalLink className="size-4" />
        </Button>
      </a>
    </div>
  );
}
