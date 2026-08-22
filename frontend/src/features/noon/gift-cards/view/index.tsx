import { getLocale, getTranslations } from "next-intl/server";
import { ArrowLeftIcon, ArrowRightIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import GiftCardForm from "./components/gift-card-form";
import type { GiftCardCategory } from "../data";

type Props = {
  category: GiftCardCategory;
};

export default async function GiftCardView({ category }: Props) {
  const t = await getTranslations("giftCards");
  const locale = await getLocale();
  const categoryLabel = t(category.labelKey);

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

      <GiftCardForm category={category} />
    </div>
  );
}
