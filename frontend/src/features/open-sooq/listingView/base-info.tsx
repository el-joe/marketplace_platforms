import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { IProduct } from "@/types";
import { BadgeCheckIcon, ChevronLeft, ChevronRight } from "lucide-react";
import { getLocale, getTranslations } from "next-intl/server";
import React from "react";

type Props = {
  product: IProduct;
};

export default async function BaseInfo({ product }: Props) {
  const locale = await getLocale();
  const t = await getTranslations("productView");
  return (
    <>
      {/* product name */}
      <h3 className="text-xl mt-4 mb-2 font-semibold">{product.title}</h3>
      {/* price */}
      <div className="flex mt-4 mb-2">
        <Price size="xl" currentPrice={product.price} />
      </div>
    </>
  );
}
