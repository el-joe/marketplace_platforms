"use client";
import { useCartContext } from "@/src/providers/cart-provider";
import { PlacementBanner } from "@/src/components/shared/placement-banner";

export default function TopBannerSlides() {
  const { cart } = useCartContext();
  return (
    <div className="mb-4">
      <PlacementBanner banner={cart?.cart_banner} variant="cart" />
    </div>
  );
}
