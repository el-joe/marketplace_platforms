"use client";
import { Link } from "@/i18n/navigation";
import { HeartIcon, Share2Icon, StarIcon } from "lucide-react";
import React, { useEffect, useState } from "react";
import { IProductDetails } from "./types";
import { Badge } from "@/src/components/ui/badge";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import Image from "next/image";

type Props = {
  product: IProductDetails;
};

export default function SmallScreenHeader({ product }: Props) {
  const locale = useLocale();
  const t = useTranslations("productView");
  const { cart } = useCartContext();
  const isInCart = cart?.cart.items.find(
    (item) => item.listing_id === product.listing.listing_id,
  );
  const [isWishlisted, setIsWishlisted] = useState<boolean>(
    product.listing.is_wishlisted,
  );
  const { addItem, isMutating, checkItem } = useWishlistContext();
  useEffect(() => {
    (async () => {
      const response = await checkItem(product.listing.listing_id);
      if (!response) return;
      const { data } = response;
      const { in_wishlist } = data;
      setIsWishlisted(in_wishlist);
    })();
  }, [checkItem, product.listing.listing_id]);
  const handleShare = async () => {
    try {
      await navigator.share({
        title: product?.product?.name?.[locale] as string,
        text: `${product?.product?.name?.[locale] as string} - ${product?.product?.attributes_summary?.[locale] as string}`,
        url: window.location.href,
      });
    } catch {
      // User cancelled share
    }
  };
  return (
    <div className="md:hidden mb-1">
      {/* brand link */}
      <div className="flex items-center gap-3 flex-wrap">
        <Link
          href={`/brands/${product.product.brand.slug}`}
          className="flex items-center gap-1 text-blue!"
        >
          {/* <BadgeCheckIcon size={"18px"} /> */}
          <p className="text-lg font-semibold">
            {product.product.brand.name[locale]}
          </p>
        </Link>
      </div>
      {/* product name + variant name */}
      <h3 className="text-lg mb-1 font-semibold">
        {product?.variant?.variant_name?.[locale] ||
          product?.product?.name?.[locale]}
      </h3>
      <div className="flex gap-1">
        {/* rate */}
        <div className="me-auto bg-gray-2 rounded-sm flex items-center gap-1 w-fit px-2 py-px md:py-0.5 mb-1">
          <StarIcon className="size-3 text-green fill-green" />
          <p className="font-semibold text-sm">{product?.product.rating_avg}</p>
          <p className="text-gray text-sm">({product?.product.rating_count})</p>
        </div>
        {!!isInCart && (
          <Badge className="text-sm rounded-sm border border-green text-green bg-white">
            <Image
              src="/images/shopping-cart-green.svg"
              alt=""
              width={18}
              height={18}
              className="invert-100"
            />{" "}
            {t("inYourCart")}
          </Badge>
        )}
        {/* wishlist button */}
        <Button
          variant={"ghost"}
          className={"aspect-square bg-gray-2"}
          disabled={isMutating}
          onClick={() => {
            addItem({
              listingId: product.listing.listing_id,
              productVariantId: product.variant.id,
            });
          }}
        >
          {isMutating ? (
            <Spinner />
          ) : (
            <HeartIcon
              className={`size-4 ${isWishlisted ? "text-[#0b5893] fill-[#0b5893]" : ""} `}
            />
          )}
        </Button>
        <Button className={"aspect-square bg-gray-2"} onClick={handleShare}>
          <Share2Icon className="size-4" />
        </Button>
      </div>
    </div>
  );
}
