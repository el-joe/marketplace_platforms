"use client";
import { Button } from "@/src/components/ui/button";
import AddressCard from "@/src/features/noon/checkout/address-card";
import DeliveryInstructionsCard from "@/src/features/noon/checkout/delivery-instructions-card";
import ItemsList from "@/src/features/noon/checkout/items-list";
import PaymentMethodsCard from "@/src/features/noon/checkout/payment-methods-card";
import PaymentSummary from "@/src/features/noon/checkout/payment-summary";
import OrderReceiverCard from "@/src/features/noon/checkout/order-receiver-card";
import LocationDialog from "@/src/components/shared/dialogs/LocationDialog";
import { Skeleton } from "@/src/components/ui/skeleton";
import { useCheckout } from "./helpers/use-checkout";
import { useTranslations } from "next-intl";
import { Spinner } from "@/src/components/ui/spinner";

export default function Checkout() {
  const t = useTranslations("checkout");
  const {
    isPreparingCheckout,
    checkoutData,
    addressesData,
    isGettingAddresses,
    handleGatewayChange,
    createOrder,
    isCreatingOrder,
    selectedGatewayId,
    isGettingGateways,
  } = useCheckout();
  if (
    (isPreparingCheckout && !checkoutData) ||
    !checkoutData ||
    !selectedGatewayId ||
    isGettingAddresses ||
    isGettingGateways
  ) {
    return (
      <div className="h-screen flex flex-col container py-12 gap-4">
        {Array.from({ length: 8 }).map((e, i) => (
          <Skeleton key={i} className="min-h-60" />
        ))}
      </div>
    );
  }
  if (!addressesData?.length) {
    return (
      <div className="h-screen flex flex-col container py-12 gap-4">
        {Array.from({ length: 8 }).map((e, i) => (
          <Skeleton key={i} className="min-h-60" />
        ))}
        <LocationDialog open />;
      </div>
    );
  }

  return (
    <div className="bg-gray-4">
      <div className="container py-8">
        <div className="max-w-304 mx-auto">
          {isPreparingCheckout && (
            <div className="fixed inset-0 bg-black/35 z-10">
              <Spinner className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-22 text-white" />
            </div>
          )}
          <div className="flex gap-4 lg:gap-8 flex-wrap px-4 items-start">
            {/* left col */}
            <div className="flex flex-col gap-5 flex-1">
              <AddressCard addressId={checkoutData?.address?.id} />
              <div className="flex gap-5 flex-wrap">
                <OrderReceiverCard />
                <DeliveryInstructionsCard
                  instructions={checkoutData?.delivery_instructions}
                />
              </div>
              <ItemsList shipment_groups={checkoutData?.shipment_groups} />
              <PaymentMethodsCard
                methods={checkoutData?.available_payment_gateways}
                selectedPaymentMethod={selectedGatewayId}
                setPaymentMethod={handleGatewayChange}
              />
            </div>
            {/* right col */}
            <div className="flex flex-col gap-8 flex-1 md:flex-[.5] sticky top-28">
              <PaymentSummary
                checkoutSummary={{
                  ...checkoutData?.order_summary,
                  item_count: checkoutData?.total_items_qty,
                }}
              />
              {/* place order button */}
              <Button
                className={
                  "bg-blue text-white h-15 w-full rounded-[16px] text-xl"
                }
                disabled={isCreatingOrder}
                onClick={createOrder}
              >
                {isCreatingOrder && <Spinner />}
                {t("placeOrder")}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
