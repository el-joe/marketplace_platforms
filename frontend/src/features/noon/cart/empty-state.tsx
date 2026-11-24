import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import CarouselProducts from "@/src/components/shared/CarouselProducts";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { PlacementBanner } from "@/src/components/shared/placement-banner";
import { hasPlacementBannerImage } from "@/src/helpers/placement-banner-images";
import { buttonVariants } from "@/src/components/ui/button";

/** Where the empty-cart banner leads when the admin banner has no link (no deals page exists yet). */
const EMPTY_CART_FALLBACK_HREF = "/";

export default function EmptyState() {
  const t = useTranslations("cart");
  const locale = useLocale();
  const { cart } = useCartContext();
  const banner = cart?.cart_banner;

  return (
    <div className="flex flex-col gap-8">
      {/* Admin-managed via Banners → placement "Cart Page Banner"; the bundled artwork is the fallback. */}
      {hasPlacementBannerImage(banner, locale) ? (
        <PlacementBanner
          banner={banner}
          variant="cartEmpty"
          fallbackHref={EMPTY_CART_FALLBACK_HREF}
        />
      ) : (
        <Link href={EMPTY_CART_FALLBACK_HREF} className="block">
          <Image
            src={`/images/empty_cart_${locale}.avif`}
            alt={t("emptyCartBannerAlt")}
            width={1216}
            height={253}
            className="w-full h-auto"
          />
        </Link>
      )}
      {/* Explicit text CTA: the artwork alone did not read as clickable to QA (client retest R8). */}
      <div className="flex flex-col items-center gap-3 text-center">
        <p className="text-lg font-semibold">{t("emptyCartTitle")}</p>
        <Link
          href={EMPTY_CART_FALLBACK_HREF}
          className={buttonVariants({
            className:
              "h-11 px-8 rounded-xl bg-blue-3 text-white font-bold hover:bg-blue-3/90",
          })}
        >
          {t("emptyCartCta")}
        </Link>
      </div>
      <div className="rounded-[16px] bg-white">
        <CarouselProducts title={t("bestsellersForYou")} />
      </div>
    </div>
  );
}
