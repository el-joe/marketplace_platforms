import { getTranslations } from "next-intl/server";
import Pagination from "@/src/components/shared/pagination";
import { getMyContracts } from "@/src/services/customer-contracts";
import ContractCard from "./components/contract-card";

type Props = {
  page: number;
};

export default async function CustomerContracts({ page }: Props) {
  const t = await getTranslations("myContracts");
  const { items, meta } = await getMyContracts(page);

  return (
    <div>
      <h1 className="text-[28px] font-bold text-primary">{t("title")}</h1>
      <p className="text-sm text-gray mt-1">{t("subtitle")}</p>

      {items.length === 0 ? (
        <p className="py-16 text-center text-sm text-gray">{t("empty")}</p>
      ) : (
        <div className="mt-6 flex flex-col gap-3">
          {items.map((contract) => (
            <ContractCard key={contract.id} contract={contract} />
          ))}
        </div>
      )}

      {meta.last_page > 1 && <Pagination totalPages={meta.last_page} />}
    </div>
  );
}
