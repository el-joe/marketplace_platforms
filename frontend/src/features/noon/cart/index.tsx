"use client";
import CouponInputCard from "@/src/features/noon/cart/coupon-input-card";
import CartItems from "@/src/features/noon/cart/cart-items";
import OrderSummary from "@/src/features/noon/cart/order-summary";
import RecommendedSection from "@/src/features/noon/cart/RecommendedSection";
import SavingsAdnBenefitsCard from "@/src/features/noon/cart/savings-and-benefits-card";
import EmptyState from "./empty-state";
import TopBannerSlides from "./top-banner-slides";
import { useCartContext } from "@/src/providers/cart-provider";
import { Skeleton } from "@/src/components/ui/skeleton";
import CheckoutButton from "./checkout-button";

export default function Cart() {
  const { cart, isLoading } = useCartContext();
  const isEmpty = !!cart?.cart.items.length;
  return (
    <div className="bg-gray-4 py-4 lg:py-8">
      <div className="max-w-304 mx-auto">
        {isLoading ? (
          <div className="flex flex-col gap-4 w-full">
            {Array.from({ length: 6 }).map((e, i) => (
              <Skeleton key={i} className="w-full h-32" />
            ))}
          </div>
        ) : !isEmpty ? (
          <EmptyState />
        ) : (
          <>
            <TopBannerSlides />
            <div className="flex gap-4 lg:gap-8 flex-wrap px-4 lg:px-0">
              <div className="flex flex-col gap-8 flex-1 max-w-full">
                <CartItems />
                <RecommendedSection />
              </div>
              <div className="flex flex-col gap-8 flex-1">
                <OrderSummary />
                {/* checkout button */}
                <CheckoutButton />
                {/* coupon input box */}
                <CouponInputCard />
                {/* savings and benefits */}
                <SavingsAdnBenefitsCard />
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
