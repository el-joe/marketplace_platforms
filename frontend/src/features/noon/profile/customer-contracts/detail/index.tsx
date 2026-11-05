import { format } from "date-fns";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Badge } from "@/src/components/ui/badge";
import Card from "@/src/components/shared/Card";
import type { MyContractDetail } from "@/src/services/customer-contracts";
import ContractDocument from "../components/contract-document";
import { STATUS_VARIANT } from "../helpers/constants";

type Props = {
  contract: MyContractDetail;
};

export default async function CustomerContractDetail({ contract }: Props) {
  const t = await getTranslations("myContracts");

  const facts: [string, string | null][] = [
    [t("listing"), [contract.listing.title, contract.listing.number].filter(Boolean).join(" · ")],
    [t("category"), contract.category?.name ?? null],
    [t("country"), contract.country?.name ?? null],
    [t("purposeLabel"), t(`purpose.${contract.purpose}`)],
    [t("versionLabel"), t("version", { version: contract.template_version })],
    [t("signerName"), contract.signer_name],
    [t("acceptedAt"), format(new Date(contract.signed_at), "MMM d, yyyy HH:mm")],
  ];

  return (
    <div className="flex flex-col gap-4">
      <Link href="/my-contracts" className="text-sm text-blue underline w-fit">
        {t("back")}
      </Link>

      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-[28px] font-bold text-primary">{contract.name}</h1>
        <Badge variant={STATUS_VARIANT[contract.status]}>
          {t(`status.${contract.status}`)}
        </Badge>
      </div>

      {contract.status === "superseded" && (
        <p className="text-sm text-gray">{t("supersededNote")}</p>
      )}

      <Card className="grid grid-cols-1 gap-3 p-4 border border-border sm:grid-cols-2">
        {facts
          .filter(([, value]) => value)
          .map(([label, value]) => (
            <div key={label} className="min-w-0">
              <p className="text-xs text-gray">{label}</p>
              <p className="text-sm font-medium wrap-break-word">{value}</p>
            </div>
          ))}
      </Card>

      <p className="text-xs text-gray">{t("recordedNote")}</p>

      <Card className="p-4 border border-border">
        <h2 className="mb-3 text-base font-semibold">{t("acceptedText")}</h2>
        <ContractDocument contract={contract} />
      </Card>
    </div>
  );
}
