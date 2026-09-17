import Image from "next/image";
import { getLocale, getTranslations } from "next-intl/server";
import { getAvailableGiftCards } from "../../api/gift-cards.actions";

/**
 * "Use your balance across brands" strip. `gift_card_batches` has no brand
 * concept of its own — each purchasable batch already represents one
 * spendable design/theme, so this renders those (deduped by title) as the
 * strip, sourced from the same `available` catalog the list above uses.
 */
export default async function BrandsStrip() {
  const [t, locale, batches] = await Promise.all([
    getTranslations("giftCards"),
    getLocale(),
    getAvailableGiftCards("AED"),
  ]);

  const brands = Array.from(
    new Map(
      batches.map((batch) => [
        batch.id,
        { title: locale === "ar" ? batch.title_ar : batch.title_en, image: batch.image_url },
      ]),
    ).values(),
  );

  if (brands.length === 0) {
    return null;
  }

  return (
    <section className="my-12 text-center">
      <h2 className="text-3xl font-bold text-light">
        {t("useBalanceAcrossBrands")}
      </h2>
      <div className="mt-6 flex items-center justify-center gap-20 flex-wrap">
        {brands.map((brand) => (
          <Image
            key={brand.title}
            src={brand.image}
            alt={brand.title}
            width={76}
            height={54}
          />
        ))}
      </div>
    </section>
  );
}
