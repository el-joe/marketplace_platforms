import { format } from "date-fns";
import { CalendarIcon, FileTextIcon, MapPinIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import { buttonVariants } from "@/src/components/ui/button";
import Card from "@/src/components/shared/Card";
import { cn } from "@/src/lib/utils";
import type { MyContract } from "@/src/services/customer-contracts";
import { STATUS_VARIANT } from "../helpers/constants";

type Props = {
  contract: MyContract;
};

export default function ContractCard({ contract }: Props) {
  const t = useTranslations("myContracts");
  const listingLine = [contract.listing.title, contract.listing.number]
    .filter(Boolean)
    .join(" · ");
  const purposeAndVersion = [
    t(`purpose.${contract.purpose}`),
    t("version", { version: contract.template_version }),
  ].join(" · ");

  return (
    <Card className="flex flex-col gap-3 p-4 border border-border shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div className="min-w-0 flex flex-col gap-1.5">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="font-bold text-base text-primary truncate">
            {contract.name}
          </h3>
          <Badge variant={STATUS_VARIANT[contract.status]}>
            {t(`status.${contract.status}`)}
          </Badge>
        </div>
        <p className="text-sm text-light truncate">
          {listingLine}
        </p>
        <div className="flex flex-wrap gap-1.5">
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <FileTextIcon className="size-3 shrink-0" />
            {purposeAndVersion}
          </span>
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <CalendarIcon className="size-3 shrink-0" />
            {format(new Date(contract.signed_at), "MMM d, yyyy")}
          </span>
          {contract.country && (
            <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
              <MapPinIcon className="size-3 shrink-0" />
              {contract.country.name}
            </span>
          )}
        </div>
      </div>

      <Link
        href={`/my-contracts/${contract.id}`}
        className={cn(
          buttonVariants({ size: "sm" }),
          "bg-blue-3 hover:bg-blue text-white border-transparent shrink-0",
        )}
      >
        {t("view")}
      </Link>
    </Card>
  );
}
