"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useQuery } from "@tanstack/react-query";
import { ChevronRight } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import { Skeleton } from "@/src/components/ui/skeleton";
import {
  getWarrantyClaims,
  type WarrantyClaimStatus,
} from "@/src/features/noon/profile/warranty/api/warranty.actions";

const STATUS_VARIANT: Record<
  WarrantyClaimStatus,
  "blue" | "yellow" | "green" | "red" | "gray"
> = {
  submitted: "blue",
  under_review: "yellow",
  approved: "green",
  rejected: "red",
  resolved: "gray",
};

export default function WarrantyClaims() {
  const t = useTranslations("profile");

  const { data, isLoading } = useQuery({
    queryKey: ["warranty-claims"],
    queryFn: () => getWarrantyClaims(1),
  });

  if (isLoading) {
    return (
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-20 rounded-xl" />
        ))}
      </div>
    );
  }

  if (!data?.data?.length) {
    return (
      <div className="flex min-h-[70vh] flex-col items-center justify-center text-center">
        <Image src="/images/profile/claims.svg" alt="" width={321} height={231} />

        <h2 className="mt-6 text-2xl font-bold text-light">
          {t("noClaimsRequested")}
        </h2>
        <p className="mt-1 text-sm text-gray">{t("noClaimsRequestedMessage")}</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {data.data.map((claim) => (
        <Link
          key={claim.id}
          href={`/warranty-claims/${claim.claim_number}`}
          className="flex items-center justify-between rounded-xl border border-border bg-white p-4 hover:shadow-sm transition-shadow"
        >
          <div className="flex flex-col gap-1">
            <span className="text-xs text-gray font-mono">{claim.claim_number}</span>
            <span className="text-sm font-medium">{claim.product.name ?? "—"}</span>
            <span className="text-xs text-gray">
              {t(`issueTypes.${claim.issue_type}`)}
            </span>
            <span className="text-xs text-gray">{claim.created_at.slice(0, 10)}</span>
          </div>
          <div className="flex items-center gap-2">
            <Badge variant={STATUS_VARIANT[claim.status] ?? "gray"}>
              {t(`claimStatus.${claim.status}`)}
            </Badge>
            <ChevronRight className="size-4 text-gray" />
          </div>
        </Link>
      ))}
    </div>
  );
}
