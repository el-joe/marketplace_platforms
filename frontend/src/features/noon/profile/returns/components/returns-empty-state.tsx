import Image from "next/image";
import { getTranslations } from "next-intl/server";

export default async function ReturnsEmptyState() {
  const t = await getTranslations("profile");
  return (
    <div className="flex flex-col items-center text-center py-12">
      <Image
        src="/images/profile/no-returns-found.svg"
        alt={t("noReturnsRequested")}
        width={320}
        height={230}
      />
      <h2 className="font-bold mt-4 text-2xl">{t("noReturnsRequested")}</h2>
      <p className="text-sm text-gray mt-1">
        {t("noReturnsRequestedMessage", { period: t("last3Months") })}
      </p>
    </div>
  );
}
