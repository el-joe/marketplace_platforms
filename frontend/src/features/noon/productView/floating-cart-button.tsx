import React from "react";
import CartButton from "./cart-button";
import { ProductCustomAttribute } from "./types/product-details";
import { CartListingType } from "./helpers/resolve-listing-type";
import { SizeGuide } from "@/src/components/shared/size-guide/types";

export default function FloatingCartButton({
  listingId,
  listingType,
  hasCustomAttributes = false,
  customAttributes = [],
  sizeGuide = null,
  sizeGuideImage = null,
}: {
  listingId: string;
  listingType?: CartListingType;
  hasCustomAttributes?: boolean;
  customAttributes?: ProductCustomAttribute[];
  sizeGuide?: SizeGuide | null;
  sizeGuideImage?: string | null;
}) {
  return (
    <div className="fixed bottom-15 md:bottom-0 inset-x-0 py-2 bg-white shadow-lg flex items-stretch z-10 pe-2 lg:hidden">
      <CartButton
        listingId={listingId}
        listingType={listingType}
        hasCustomAttributes={hasCustomAttributes}
        customAttributes={customAttributes}
        sizeGuide={sizeGuide}
        sizeGuideImage={sizeGuideImage}
      />
    </div>
  );
}
