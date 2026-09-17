"use client";
import React, { useEffect, useRef } from "react";
import { IProductDetails } from "./types";
import Image from "next/image";
import useLocale from "@/src/hooks/use-locale";
import Price from "@/src/components/shared/Price";
import CartButton from "./cart-button";
import { getImageURL } from "@/src/helpers/get-image-url";
import { getListingImage } from "@/src/types/media";

type Props = {
  product: IProductDetails;
};

export default function FloatingProductSummary({ product }: Props) {
  const containerRef = useRef<null | HTMLDivElement>(null);
  const locale = useLocale();
  useEffect(() => {
    const handleShow = () => {
      if (window.scrollY >= 800) {
        containerRef?.current?.classList.replace("-bottom-28", "bottom-4");
      } else if (window.scrollY < 800) {
        containerRef?.current?.classList.replace("bottom-4", "-bottom-28");
      }
    };
    window.addEventListener("scroll", () => handleShow());
    return window.removeEventListener("scroll", () => handleShow());
  }, []);
  return (
    <div
      ref={containerRef}
      className={`hidden fixed -bottom-28 bg-white shadow-lg rounded-3xl px-6 py-3 inset-s-1/2 ${locale === "ar" ? "translate-x-1/2" : "-translate-x-1/2"} z-50 lg:flex items-center gap-4 max-w-[100vw] transition-all duration-500`}
    >
      <Image
        src={getImageURL(
          getListingImage({
            images: product.product.images.length
              ? [
                  product.product.images.find((e) => e.is_primary) ||
                    product.product.images[0],
                ]
              : [],
          }),
        )}
        alt={product.product.name.en as string}
        width={60}
        height={60}
      />
      <div>
        <h3 className="line-clamp-1 text-base font-semibold">
          {product.variant?.variant_name?.[locale] ||
            product.product.name[locale]}
        </h3>
        <Price
          size="lg"
          currentPrice={product.listing.price}
          currency={product.listing.currency}
          // oldPrice={product.oldPrice}
          // discountPercent={product.discount}
        />
      </div>
      <div className="w-58">
        <CartButton
          listingId={product.listing.listing_id}
          hasCustomAttributes={!!product.product.has_custom_attributes}
          customAttributes={product.product.custom_attributes ?? []}
        />
      </div>
    </div>
  );
}
