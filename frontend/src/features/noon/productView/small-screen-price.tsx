"use client";
import React from "react";
import { IProductDetails } from "./types";
import Price from "@/src/components/shared/Price";
import { useTranslations } from "next-intl";
import { Badge } from "@/src/components/ui/badge";
import useCountDown from "@/src/hooks/useCountDown";
import AnimatedBadge from "@/src/components/shared/animated-badge";
import { CarIcon, TruckIcon } from "lucide-react";
import useLocale from "@/src/hooks/use-locale";

type Props = { product: IProductDetails };
const PROMO_BADGE_ICONS: Record<
  string,
  React.ForwardRefExoticComponent<
    Omit<React.ComponentProps<typeof CarIcon>, "ref"> &
      React.RefAttributes<SVGSVGElement>
  >
> = {
  car: CarIcon,
  truck: TruckIcon,
};

export default function SmallScreenPrice({ product }: Props) {
  const t = useTranslations("productView");
  const locale = useLocale();
  const { H, M } = useCountDown(
    new Date(product.flash_sale_ends_at || new Date().getDate()),
  );
  return (
    <div className="md:hidden mt-2">
      <div className="flex mb-2 gap-2 items-center">
        <Price
          size="xl"
          currentPrice={product.listing.price}
          currency={product.listing.currency}
          // oldPrice={product.oldPrice}
          // discountPercent={product.discount}
          className="flex-col"
        />
        <p className="text-sm text-gray">{t("inclusiveOfVat")}</p>
      </div>
      {product.is_flash_sale ? (
        <Badge className="text-sm font-bold bg-[#f5ced7] text-red rounded-sm">
          {t("flashSale")}
          {!!product.flash_sale_ends_at && (
            <span className="ms-1">
              {H}h {M}m
            </span>
          )}
        </Badge>
      ) : (
        product.is_mega_deal && (
          <Badge className="text-sm font-bold bg-[#f5ced7] text-red rounded-sm">
            {t("megaDeal")}
          </Badge>
        )
      )}
      {!!product.promo_badges?.length && (
        <AnimatedBadge
          badges={product.promo_badges.map((badge) => ({
            label: badge.label[locale],
            icon: PROMO_BADGE_ICONS[badge.icon_key] || CarIcon,
            iconColor: badge.color_hex,
          }))}
          containerClasses="px-2! bg-gray-2! rounded-md!"
        />
      )}
    </div>
  );
}
