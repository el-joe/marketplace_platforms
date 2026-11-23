import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import CarouselProducts from "@/src/components/shared/CarouselProducts";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { PlacementBanner } from "@/src/components/shared/placement-banner";
import { hasPlacementBannerImage } from "@/src/helpers/placement-banner-images";

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
      <div className="rounded-[16px] bg-white">
        <CarouselProducts title={t("bestsellersForYou")} />
      </div>
    </div>
  );
}
