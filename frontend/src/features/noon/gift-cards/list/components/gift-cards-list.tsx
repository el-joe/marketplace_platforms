import { SearchIcon, ZapIcon } from "lucide-react";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Link } from "@/i18n/navigation";
import { getLocale, getTranslations } from "next-intl/server";
import { getAvailableGiftCards } from "../../api/gift-cards.actions";
import GiftCardOfferCard from "./gift-card-offer-card";
import MyGiftCardsDialog from "./my-gift-cards-dialog";

export default async function GiftCardsList() {
  const [t, locale, giftCards] = await Promise.all([
    getTranslations("giftCards"),
    getLocale(),
    getAvailableGiftCards("AED"),
  ]);

  return (
    <section className="max-w-[1000px] mx-auto mt-10">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 className="text-2xl font-bold">{t("shopByCategory")}</h2>
        <div className="flex items-center gap-2">
          <Input
            startIcon={<SearchIcon className="size-4 text-gray" />}
            placeholder={t("searchByCategory")}
            className="h-10 w-56"
          />
          <Link
            href="/noon-credits"
            className="flex h-10 items-center gap-1.5 rounded-lg border border-blue px-3 text-sm font-medium text-blue"
          >
            <ZapIcon className="size-4" />
            {t("redeemGiftCard")}
          </Link>
          <MyGiftCardsDialog />
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {giftCards.map((giftCard) => (
          <GiftCardOfferCard
            key={giftCard.id}
            giftCard={giftCard}
            title={locale === "ar" ? giftCard.title_ar : giftCard.title_en}
          />
        ))}
      </div>
    </section>
  );
}
