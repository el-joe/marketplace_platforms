"use client";
import { Button } from "@/src/components/ui/button";
import AddressCard from "@/src/features/noon/checkout/address-card";
import DeliveryInstructionsCard from "@/src/features/noon/checkout/delivery-instructions-card";
import ItemsList from "@/src/features/noon/checkout/items-list";
import PaymentMethodsCard from "@/src/features/noon/checkout/payment-methods-card";
import OfflinePaymentProofCard from "@/src/features/noon/checkout/offline-payment-proof-card";
import PaymentSummary from "@/src/features/noon/checkout/payment-summary";
import OrderReceiverCard from "@/src/features/noon/checkout/order-receiver-card";
import LocationDialog from "@/src/components/shared/dialogs/address-dialog/address-dialog";
import { Skeleton } from "@/src/components/ui/skeleton";
import { useCheckout } from "./helpers/use-checkout";
import { useTranslations } from "next-intl";
import { Spinner } from "@/src/components/ui/spinner";
import MarketerContractModal from "./marketer-contract-modal";
import { PlacementBanner } from "@/src/components/shared/placement-banner";

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
    selectedInstruction,
    setSelectedInstruction,
    selectedReceiverId,
    setSelectedReceiverId,
    contract,
    isContractModalOpen,
    closeContractModal,
    acceptContract,
    isAcceptingContract,
    isOfflinePaymentMethod,
    offlineProofFile,
    setOfflineProofFile,
    offlineProofNote,
    setOfflineProofNote,
    isPlacingOrder,
    isUploadingProof,
  } = useCheckout();
  if (
    (isPreparingCheckout && !checkoutData) ||
    !checkoutData ||
    !selectedGatewayId ||
    isGettingAddresses ||
    isGettingGateways
  ) {
    return (
      <div className="h-screen overflow-hidden flex flex-col container py-12 gap-4">
        {Array.from({ length: 8 }).map((e, i) => (
          <Skeleton key={i} className="min-h-60" />
        ))}
      </div>
    );
  }
  if (!addressesData?.length) {
    return (
      <div className="h-screen overflow-hidden flex flex-col container py-12 gap-4">
        {Array.from({ length: 8 }).map((e, i) => (
          <Skeleton key={i} className="min-h-60" />
        ))}
        <LocationDialog open />
      </div>
    );
  }

  return (
    <div className="bg-gray-4">
      <div className="container py-10">
        <div className="max-w-304 mx-auto">
          {checkoutData?.checkout_banner && (
            <div className="mb-4 px-4 lg:px-0">
              <PlacementBanner
                banner={checkoutData.checkout_banner}
                variant="checkout"
              />
            </div>
          )}
          {isPreparingCheckout && (
            <div className="fixed inset-0 bg-black/35 z-10">
              <Spinner className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-22 text-white" />
            </div>
          )}
          <div className="flex gap-4 lg:gap-8 flex-wrap px-4s items-start">
            {/* left col */}
            <div className="flex flex-col gap-5 flex-1 max-w-full md:max-w-2/3">
              <AddressCard addressId={checkoutData?.address?.id} />
              <div className="flex gap-5 flex-wrap">
                <OrderReceiverCard
                  selectedReceiverId={selectedReceiverId}
                  onSelectReceiver={(receiver) =>
                    setSelectedReceiverId(receiver.id)
                  }
                />
                <DeliveryInstructionsCard
                  instructions={checkoutData?.delivery_instructions ?? []}
                  selectedInstruction={selectedInstruction}
                  onSelect={setSelectedInstruction}
                />
              </div>
              <ItemsList shipment_groups={checkoutData?.shipment_groups} />
              <PaymentMethodsCard
                methods={checkoutData?.available_payment_gateways}
                selectedPaymentMethod={selectedGatewayId}
                setPaymentMethod={handleGatewayChange}
                total={checkoutData?.order_summary?.total ?? 0}
                walletBalance={checkoutData?.wallet_balance ?? 0}
              />
              {isOfflinePaymentMethod && (
                <OfflinePaymentProofCard
                  file={offlineProofFile}
                  setFile={setOfflineProofFile}
                  note={offlineProofNote}
                  setNote={setOfflineProofNote}
                />
              )}
            </div>
            {/* right col */}
            <div className="flex flex-col gap-8 flex-1 md:flex-[.5] sticky top-25">
              <PaymentSummary
                checkoutSummary={{
                  ...checkoutData?.order_summary,
                  item_count: checkoutData?.total_items_qty,
                }}
                walletDeduction={
                  checkoutData?.wallet_applicable
                    ? Math.min(
                        checkoutData.wallet_balance,
                        checkoutData?.order_summary?.total ?? 0,
                      )
                    : 0
                }
              />
              {/* place order button */}
              <Button
                className={
                  "bg-blue text-white h-15 w-full rounded-[16px] text-xl"
                }
                disabled={
                  isCreatingOrder ||
                  (isOfflinePaymentMethod && !offlineProofFile)
                }
                onClick={createOrder}
              >
                {isCreatingOrder && <Spinner />}
                {isPlacingOrder
                  ? t("placingOrder")
                  : isUploadingProof
                    ? t("uploadingProof")
                    : t("placeOrder")}
              </Button>
            </div>
          </div>
        </div>
      </div>
      {contract && (
        <MarketerContractModal
          open={isContractModalOpen}
          contract={contract}
          onAccept={acceptContract}
          onClose={closeContractModal}
          isSubmitting={isAcceptingContract}
        />
      )}
    </div>
  );
}
