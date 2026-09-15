import React from "react";
import CartButton from "./cart-button";
import { ProductCustomAttribute } from "./types/product-details";

export default function FloatingCartButton({
  listingId,
  hasCustomAttributes = false,
  customAttributes = [],
}: {
  listingId: string;
  hasCustomAttributes?: boolean;
  customAttributes?: ProductCustomAttribute[];
}) {
  return (
    <div className="fixed bottom-15 md:bottom-0 inset-x-0 py-2 bg-white shadow-lg flex items-stretch z-10 pe-2 lg:hidden">
      <CartButton
        listingId={listingId}
        hasCustomAttributes={hasCustomAttributes}
        customAttributes={customAttributes}
      />
    </div>
  );
}
