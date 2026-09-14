"use client";
import Image from "next/image";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import { getImageURL } from "@/src/helpers/get-image-url";
import { apiBaseUrl } from "@/src/lib/utils";
import { CrossSellAd } from "./types/product-details";

interface Props {
  ad: CrossSellAd;
}

const AdBar = ({ ad }: Props) => {
  const locale = useLocale();
  const t = useTranslations("productView");

  const handleClick = () => {
    fetch(`${apiBaseUrl}/ads/sponsored/click`, {
      method: "POST",
      keepalive: true,
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        impression_id: ad.impression_id,
        vendor_listing_id: ad.listing_id,
      }),
    }).catch(() => {});
  };

  const name = (ad.name?.[locale] ?? ad.name?.en ?? "") as string;
  const badge = ad.shipping_badge;
  const badgeLabel = badge ? (badge.label?.[locale] ?? badge.label?.en) : null;

  return (
    <div className="bg-[#fafafa] mb-4 border-b border-gray-100">
      <div className="container">
        <Link
          href={`/products/${ad.url_param}`}
          onClick={handleClick}
          className="flex items-center justify-center gap-2 relative pe-10 md:pe-14 py-1.5 hover:bg-gray-50 transition-colors"
        >
          {ad.thumbnail && (
            <Image
              src={getImageURL(ad.thumbnail)}
              alt={name}
              width={32}
              height={32}
              className="rounded object-contain shrink-0"
            />
          )}

          <p className="max-w-[50%] md:max-w-[60%] line-clamp-1 text-xs md:text-sm text-gray-800">
            {name}
          </p>

          <Price currentPrice={ad.price} currency={ad.currency} size="sm" />

          {badge && (
            <span
              className="px-1.5 py-0.5 font-bold rounded text-xs shrink-0"
              style={{
                background: badge.color_hex,
                color: badge.text_color_hex,
              }}
            >
              {badgeLabel}
            </span>
          )}

          <span className="absolute bottom-0 inset-e-0.5 md:inset-e-4 text-[10px] md:text-xs font-semibold bg-[#e7e7e7] rounded-tl px-2 py-0.5 text-gray-500">
            {t("ad")}
          </span>
        </Link>
      </div>
    </div>
  );
};

export default AdBar;
