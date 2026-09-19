"use client";

import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { ChevronRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import useLocale from "@/src/hooks/use-locale";
import { getSpecialRequests } from "./api/special-requests.actions";
import { STATUS_VARIANT } from "./helpers/constants";
import { useRequestActions } from "./helpers/use-request-actions";

export default function SpecialRequests() {
  const t = useTranslations("specialRequests");
  const locale = useLocale();
  const { closeRequest, isClosing } = useRequestActions();

  const { data, isLoading } = useQuery({
    queryKey: ["special-requests"],
    queryFn: () => getSpecialRequests(),
  });

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold">{t("myRequests")}</h1>
        <Button
          render={<Link href="/special-requests/create" />}
          nativeButton={false}
          className="h-10 rounded-md bg-blue-3 px-5 text-white"
        >
          {t("newRequest")}
        </Button>
      </div>

      {isLoading && [1, 2, 3].map((i) => <Skeleton key={i} className="h-20 rounded-xl" />)}

      {!isLoading && !data?.data?.length && (
        <p className="py-16 text-center text-sm text-gray">{t("empty")}</p>
      )}

      {data?.data?.map((r) => (
        <div
          key={r.id}
          className="flex items-center justify-between gap-3 rounded-xl border border-border bg-white p-4"
        >
          <Link href={`/special-requests/${r.id}`} className="flex-1 space-y-1">
            <span className="block text-sm font-medium">
              {(locale === "ar" && r.title_ar) || r.title_en}
            </span>
            <span className="block text-xs text-gray">
              {(locale === "ar" && r.category?.name_ar) || r.category?.name_en}
              {"\u00B7"}
              {r.city
                ? (locale === "ar" && r.city.name_ar) || r.city.name_en
                : t("allCities")}
            </span>
            <span className="block text-xs text-gray">{r.created_at.slice(0, 10)}</span>
          </Link>
          <Badge variant={STATUS_VARIANT[r.status] ?? "gray"}>
            {t(`status.${r.status}`)}
          </Badge>
          {r.status !== "closed" && (
            <Button
              variant="outline"
              disabled={isClosing}
              onClick={() => closeRequest(r.id)}
            >
              {t("close")}
            </Button>
          )}
          <ChevronRight className="size-4 text-gray rtl:rotate-180" />
        </div>
      ))}
    </div>
  );
}
