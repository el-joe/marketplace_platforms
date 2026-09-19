"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { Badge } from "@/src/components/ui/badge";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import useLocale from "@/src/hooks/use-locale";
import { getSpecialRequest } from "../api/special-requests.actions";
import { STATUS_VARIANT } from "../helpers/constants";
import { useRequestActions } from "../helpers/use-request-actions";

export default function SpecialRequestDetail({ id }: { id: string }) {
  const t = useTranslations("specialRequests");
  const locale = useLocale();
  const { closeRequest, isClosing } = useRequestActions();

  const { data: r, isLoading } = useQuery({
    queryKey: ["special-requests", id],
    queryFn: () => getSpecialRequest(id),
  });

  if (isLoading || !r) return <Skeleton className="h-64 rounded-xl" />;

  const pick = (ar: string | null, en: string | null) =>
    (locale === "ar" && ar) || en || "—";

  return (
    <div className="space-y-4 rounded-xl border border-border bg-white p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold">{pick(r.title_ar, r.title_en)}</h1>
        <Badge variant={STATUS_VARIANT[r.status] ?? "gray"}>
          {t(`status.${r.status}`)}
        </Badge>
      </div>
      <p className="whitespace-pre-line text-sm">
        {pick(r.description_ar, r.description_en)}
      </p>
      <dl className="grid grid-cols-2 gap-3 text-sm">
        <dt className="text-gray">{t("category")}</dt>
        <dd>{r.category ? pick(r.category.name_ar, r.category.name_en) : "—"}</dd>
        <dt className="text-gray">{t("city")}</dt>
        <dd>{r.city ? pick(r.city.name_ar, r.city.name_en) : t("allCities")}</dd>
        <dt className="text-gray">{t("budget")}</dt>
        <dd>{r.budget != null ? `${r.budget} ${r.budget_currency ?? ""}` : "—"}</dd>
        <dt className="text-gray">{t("brokersNotifiedLabel")}</dt>
        <dd>{r.brokers_notified}</dd>
      </dl>
      {r.status !== "closed" && (
        <Button variant="outline" disabled={isClosing} onClick={() => closeRequest(r.id)}>
          {t("close")}
        </Button>
      )}
    </div>
  );
}
