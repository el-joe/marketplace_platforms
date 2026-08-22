import Image from "next/image";
import { getTranslations } from "next-intl/server";

export default async function PaymentsEmptyState() {
  const t = await getTranslations("profile");
  return (
    <div className="flex flex-col items-center text-center py-12 lg:max-w-xl mx-auto">
      <Image
        src="/images/profile/no-payment.svg"
        alt={t("paymentsEmptyMessage")}
        width={320}
        height={230}
      />
      <h2 className="font-bold mt-4 text-2xl">{t("paymentsEmptyTitle")}</h2>
      <p className="text-sm text-gray mt-1">{t("paymentsEmptyMessage")}</p>
    </div>
  );
}
