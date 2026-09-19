import { Link } from "@/i18n/navigation";
import Logo from "@/src/components/shared/Logo";
import getLocale from "@/src/helpers/getLocale";
import { ArrowLeftIcon, ArrowRightIcon, LockIcon } from "lucide-react";
import { getTranslations } from "next-intl/server";
import React from "react";

export const CheckoutHeader = async () => {
  const locale = await getLocale();
  const t = await getTranslations("checkout");
  return (
    <header className="bg-main">
      <div className="max-w-304 mx-auto py-4 px-3 lg:px-0">
        <div className="flex items-center justify-between">
          <Link href={"/cart"} className="md:flex items-center gap-2 hidden">
            {locale === "ar" ? (
              <ArrowRightIcon className="size-5" />
            ) : (
              <ArrowLeftIcon className="size-5" />
            )}
            <span className="text-text-semibold text-lg">{t("cart")}</span>
          </Link>
          <div className="hidden md:flex items-center gap-2">
            <LockIcon className="size-6" />
            <p className="text-xl font-bold">{t("secureCheckout")}</p>
          </div>
          <div className="flex gap-2">
            {locale === "ar" ? (
              <ArrowRightIcon className="size-5 md:hidden" />
            ) : (
              <ArrowLeftIcon className="size-5 md:hidden" />
            )}
            <Logo />
          </div>
        </div>
      </div>
    </header>
  );
};
