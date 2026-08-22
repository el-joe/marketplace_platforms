import Image from "next/image";
import { getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import { Button } from "@/src/components/ui/button";
import AddCreditsModal from "../add-credits-modal";

export default async function CreditsEmptyState() {
  const t = await getTranslations("profile");
  return (
    <Card className="mt-4 flex flex-col items-center text-center py-16">
      <Image
        src="/images/profile/no-transactions.svg"
        alt={t("transactionsEmptyTitle")}
        width={274}
        height={179}
      />
      <h2 className="font-bold mt-6 text-2xl">{t("transactionsEmptyTitle")}</h2>
      <p className="text-md text-gray mt-1">{t("transactionsEmptyMessage")}</p>

      <AddCreditsModal
        trigger={
          <Button className="bg-black text-white uppercase font-semibold px-8 h-12 rounded-md mt-6 md:w-md w-full">
            {t("addCredits")}
          </Button>
        }
      />
    </Card>
  );
}
