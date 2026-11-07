import { CalendarIcon, FileTextIcon, MapPinIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import { buttonVariants } from "@/src/components/ui/button";
import Card from "@/src/components/shared/Card";
import LocalDateTime from "@/src/components/shared/local-date-time";
import { cn } from "@/src/lib/utils";
import type { MyContract } from "@/src/services/customer-contracts";
import { STATUS_VARIANT } from "../helpers/constants";
import ContractDownloadButton from "./contract-download-button";

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
            <LocalDateTime value={contract.signed_at} withTime={false} />
          </span>
          {contract.country && (
            <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
              <MapPinIcon className="size-3 shrink-0" />
              {contract.country.name}
            </span>
          )}
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-2">
        {contract.download_url && (
          <ContractDownloadButton
            downloadUrl={contract.download_url}
            fileName={contract.file_name ?? `contract-${contract.listing.number ?? contract.id}.html`}
            label={t("download")}
            failedMessage={t("downloadFailed")}
          />
        )}
        <Link
          href={`/my-contracts/${contract.id}`}
          className={cn(
            buttonVariants({ size: "sm" }),
            "bg-blue-3 hover:bg-blue text-white border-transparent shrink-0",
          )}
        >
          {t("view")}
        </Link>
      </div>
    </Card>
  );
}
