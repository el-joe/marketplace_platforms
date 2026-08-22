import React from "react";
import CartButton from "./cart-button";

export default function FloatingCartButton({
  listingId,
}: {
  listingId: string;
}) {
  return (
    <div className="fixed bottom-15 md:bottom-0 inset-x-0 py-2 bg-white shadow-lg flex items-stretch z-10 pe-2 lg:hidden">
      <CartButton listingId={listingId} />
    </div>
  );
}
