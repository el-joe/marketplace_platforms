import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";

export default async function CreateWarrantyClaim() {
  const t = await getTranslations("profile");

  return (
    <div className="flex min-h-[70vh] flex-col items-center justify-center text-center">
      <Image
        src="/images/profile/claims.svg"
        alt=""
        width={321}
        height={231}
      />

      <h2 className="mt-6 text-2xl font-bold">{t("noClaimableItems")}</h2>
      <p className="mt-1 text-sm text-gray">{t("noClaimableItemsMessage")}</p>

      <Button
        render={<Link href="/warranty-claims" />}
        nativeButton={false}
        className="mt-6 h-12 rounded-md bg-blue-3 px-8 font-semibold text-white uppercase"
      >
        {t("backToExistingClaims")}
      </Button>
    </div>
  );
}
