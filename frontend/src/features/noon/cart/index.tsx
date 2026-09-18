"use client";
import CouponInputCard from "@/src/features/noon/cart/coupon-input-card";
import CartItems from "@/src/features/noon/cart/cart-items";
import OrderSummary from "@/src/features/noon/cart/order-summary";
import SuggestedProductsSection from "@/src/features/noon/cart/suggested-products-section";
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
            <div className="flex gap-4 flex-col md:flex-row lg:gap-8 flex-wrap px-4 lg:px-0 w-full">
              <div className="flex flex-col gap-8 flex-1s md:w-[440px] lg:w-[calc(100%-(428px+32px))]">
                <CartItems />
                <SuggestedProductsSection />
              </div>
              <div className="flex flex-col gap-8 flex-1 md:min-w-[260px] lg:w-[428px] lg:min-w-[428px]">
                <div className="order-3 md:-order-1">
                  <OrderSummary />
                </div>
                {/* checkout button */}
                <CheckoutButton />
                {/* coupon input box */}
                <CouponInputCard />
                {/* savings and benefits */}
                <div className="-order-1 md:order-3">
                  <SavingsAdnBenefitsCard />
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
