"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useQuery } from "@tanstack/react-query";
import { ChevronRight, ShieldCheck, ShieldOff, Clock } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import { Skeleton } from "@/src/components/ui/skeleton";
import {
  getWarrantyPurchases,
  type WarrantyPurchase,
} from "@/src/features/noon/profile/warranty/api/warranty.actions";

const STATUS_CONFIG: Record<
  WarrantyPurchase["status"],
  { variant: "green" | "blue" | "gray" | "red"; icon: React.ReactNode }
> = {
  active: { variant: "green", icon: <ShieldCheck className="size-3.5" /> },
  pending: { variant: "blue", icon: <Clock className="size-3.5" /> },
  expired: { variant: "gray", icon: <ShieldOff className="size-3.5" /> },
  cancelled: { variant: "red", icon: <ShieldOff className="size-3.5" /> },
};

export default function MyWarranties() {
  const t = useTranslations("profile");

  const { data, isLoading } = useQuery({
    queryKey: ["warranty-purchases"],
    queryFn: () => getWarrantyPurchases(1),
  });

  if (isLoading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-28 rounded-xl" />
        ))}
      </div>
    );
  }

  if (!data?.data?.length) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center text-center">
        <Image src="/images/profile/claims.svg" alt="" width={321} height={231} />

        <h2 className="mt-6 text-2xl font-bold text-light">
          {t("noWarrantyPurchases")}
        </h2>
        <p className="mt-1 text-sm text-gray">{t("noWarrantyPurchasesMessage")}</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {data.data.map((purchase) => (
        <WarrantyCard key={purchase.id} purchase={purchase} />
      ))}
    </div>
  );
}

function WarrantyCard({ purchase }: { purchase: WarrantyPurchase }) {
  const t = useTranslations("profile");
  const config = STATUS_CONFIG[purchase.status] ?? STATUS_CONFIG.expired;

  return (
    <div className="rounded-xl border border-border bg-white p-4 space-y-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold leading-snug">
            {purchase.product.name ?? t("unknownProduct")}
          </p>
          <p className="text-xs text-gray-500 mt-0.5">{purchase.product.sku}</p>
        </div>
        <Badge variant={config.variant} className="flex items-center gap-1 shrink-0">
          {config.icon}
          {t(`warrantyStatus.${purchase.status}`)}
        </Badge>
      </div>

      <div className="flex items-center gap-2">
        <ShieldCheck className="size-4 text-blue-500 shrink-0" />
        <span className="text-sm text-gray-700">
          {purchase.plan.name ?? t("unknownPlan")}
          {purchase.plan.duration_label ? ` · ${purchase.plan.duration_label}` : ""}
        </span>
      </div>

      {(purchase.coverage_starts_at || purchase.coverage_ends_at) && (
        <div className="text-xs text-gray-400 flex gap-3">
          {purchase.coverage_starts_at && (
            <span>
              {t("coverageFrom")}: {purchase.coverage_starts_at}
            </span>
          )}
          {purchase.coverage_ends_at && (
            <span>
              {t("coverageUntil")}: {purchase.coverage_ends_at}
            </span>
          )}
        </div>
      )}

      {purchase.is_claimable && (
        <Link
          href={`/warranty-claims/create?order_item_id=${purchase.order_item_id ?? ""}`}
          className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-600 font-medium hover:bg-blue-100 transition-colors"
        >
          <span>{t("fileWarrantyClaim")}</span>
          <ChevronRight className="size-4" />
        </Link>
      )}

      {purchase.status === "pending" && (
        <p className="text-xs text-gray-400 italic">{t("warrantyPendingNote")}</p>
      )}
    </div>
  );
}
