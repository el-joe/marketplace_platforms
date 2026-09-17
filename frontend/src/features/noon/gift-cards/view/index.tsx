import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import GiftCardForm from "./components/gift-card-form";
import type { GiftCardBatch } from "../helpers/types";

type Props = {
  batch: GiftCardBatch;
};

export default async function GiftCardView({ batch }: Props) {
  const [t, locale] = await Promise.all([
    getTranslations("giftCards"),
    getLocale(),
  ]);
  const categoryLabel = locale === "ar" ? batch.title_ar : batch.title_en;

  const breadCrumbList = [
    { label: t("home"), href: "/" },
    { label: t("giftCards"), href: "/gift-cards" },
    { label: categoryLabel, href: "" },
  ];

  return (
    <div className="max-w-[1000px] container py-6">
      <Breadcrumb list={breadCrumbList} />

      <Link
        href="/gift-cards"
        className="flex items-center gap-2 mt-4 mb-4 text-xl font-bold"
      >
        {locale === "ar" ? (
          <ArrowRightIcon className="size-5" />
        ) : (
          <ArrowLeftIcon className="size-5" />
        )}
        {categoryLabel}
      </Link>

      <div className="border-t border-border" />

      <GiftCardForm batch={batch} />
    </div>
  );
}
