import { Link } from "@/i18n/navigation";
import { getTranslations } from "next-intl/server";
import React from "react";

export default async function CheckoutFooter() {
  const t = await getTranslations("checkout");
  return (
    <footer className="bg-gray-4 pb-10">
      <div className="max-w-304 mx-auto py-4 px-3 lg:px-0">
        <p className="text-gray mb-6 text-sm">
          {t("footerAssistanceNumber$", { number: "8 8 0 8 8" })}
        </p>
        <p className="text-gray mb-3 text-sm">
          {t("$$AllRightsReserved", {
            date: new Date().getFullYear(),
            brand: "noon",
          })}
        </p>
        <div className="flex gap-2 items-center">
          <Link
            href={"/terms-of-use"}
            className="border-b-2 border-dotted border-black text-sm"
          >
            {t("termsOfUse")}
          </Link>
          <span>.</span>
          <Link
            href={"/terms-of-sale"}
            className="border-b-2 border-dotted border-black text-sm"
          >
            {t("termsOfSale")}
          </Link>
          <span>.</span>
          <Link
            href={"/privacy-policy"}
            className="border-b-2 border-dotted border-black text-sm"
          >
            {t("privacyPolicy")}
          </Link>
        </div>
      </div>
    </footer>
  );
}
