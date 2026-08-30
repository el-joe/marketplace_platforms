"use client";

import Image from "next/image";
import { Link } from "@/i18n/navigation";
import Price from "./Price";
import useLocale from "@/src/hooks/use-locale";
import type { Product } from "@/types/globals";

type Props = {
  productData: Product;
};

const SpecialProductCard = ({ productData }: Props) => {
  const locale = useLocale();

  const categoryName =
    locale === "ar"
      ? productData.category_name?.ar
      : productData.category_name?.en;

  const imageUrl = productData.primary_image || productData.thumbnail;

  const hasCompareAtPrice =
    !!productData.compare_at_price &&
    productData.compare_at_price > productData.price;

  return (
    <Link
      href={`/products/${productData.url_param}`}
      className="group flex flex-col bg-white rounded-xl overflow-hidden h-full border border-border-color"
    >
      <div className="relative w-full aspect-square bg-gray-50 overflow-hidden">
        {categoryName && (
          <span className="absolute top-2 start-2 z-10 bg-black/80 text-white text-[10px] font-semibold px-2 py-0.5 rounded-md leading-tight">
            {categoryName}
          </span>
        )}

        {imageUrl ? (
          <Image
            src={imageUrl}
            alt={locale === "ar" ? productData.name_ar : productData.name_en}
            fill
            className="object-contain p-3 group-hover:scale-105 transition-transform duration-300"
            sizes="(max-width: 768px) 50vw, 25vw"
          />
        ) : (
          <div className="w-full h-full bg-gray-100" />
        )}
      </div>

      <div className="flex flex-col gap-1.5 p-3 flex-1">
        <h3 className="text-xs md:text-sm font-medium text-primary line-clamp-2 leading-snug">
          {locale === "ar" ? productData.name_ar : productData.name_en}
        </h3>

        <Price
          className="mt-auto pt-1"
          currentPrice={productData.price}
          oldPrice={hasCompareAtPrice ? productData.compare_at_price! : undefined}
          currency={productData.currency}
          size="sm"
        />
      </div>
    </Link>
  );
};

export default SpecialProductCard;
