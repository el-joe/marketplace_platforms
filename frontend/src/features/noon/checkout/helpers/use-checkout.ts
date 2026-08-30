import { useMutation, useQuery } from "@tanstack/react-query";
import { createPrepareCheckoutService, placeOrderService } from "../api/post";
import { useEffect, useMemo, useState } from "react";
import { getAddresses } from "@/src/services/address";
import { v4 as uuidv4 } from "uuid";
import { useRouter } from "@/i18n/navigation";
import { IPrepareCheckout } from "../types/checkout.type";
import { getPaymentGateways } from "../api/get";

export const useCheckout = () => {
  const router = useRouter();

  const [checkoutData, setCheckoutData] = useState<
    IPrepareCheckout | undefined
  >(undefined);

  const [selectedInstruction, setSelectedInstruction] = useState<
    string | null
  >(null);

  const gateways = useQuery({
    queryKey: ["payment-gateways"],
    queryFn: getPaymentGateways,
  });
  const addresses = useQuery({
    queryKey: ["addressesList"],
    queryFn: getAddresses,
  });

  const selectedAddress = useMemo(
    () => addresses.data?.find((a) => a.is_default) ?? addresses.data?.[0],
    [addresses.data],
  );
  const selectedGatewayId = useMemo(
    () =>
      checkoutData?.available_payment_gateways.find(
        (g) => g.gateway_code === checkoutData?.gateway_code,
      )?.id,
    [checkoutData?.available_payment_gateways, checkoutData?.gateway_code],
  );

  const prepareCheckout = useMutation({
    mutationFn: createPrepareCheckoutService,
    onSuccess: (data) => setCheckoutData(data.data),
  });

  const placeOrder = useMutation({
    mutationFn: placeOrderService,
    onSuccess: (data) => {
      const order = data?.data;
      const orderNumber = order?.order_number;
      if (typeof window !== "undefined" && order) {
        try {
          sessionStorage.setItem("last_placed_order", JSON.stringify(order));
        } catch (e) {
          console.error("Failed to save order to sessionStorage:", e);
        }
        try {
          const keysToRemove: string[] = [];
          for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key?.startsWith("warranty-selection:")) keysToRemove.push(key);
          }
          keysToRemove.forEach((k) => sessionStorage.removeItem(k));
        } catch (e) {
          console.error("Failed to clear warranty selections:", e);
        }
      }
      if (order?.requires_redirect && order?.payment_redirect_url) {
        window.location.href = order.payment_redirect_url;
        return;
      }
      if (orderNumber) {
        router.push(`/checkout/success?order_number=${orderNumber}`);
      } else {
        router.push("/checkout/success");
      }
    },
  });

  const prepare = (addressId: number, gatewayId: string) => {
    prepareCheckout.mutate({
      address_id: addressId,
      country_payment_gateway_id: gatewayId,
    });
  };

  const handleGatewayChange = (gatewayId: string) => {
    if (!selectedAddress) return;
    prepare(Number(selectedAddress.id), gatewayId);
  };

  const createOrder = () => {
    if (!selectedAddress || !selectedGatewayId) return;

    const warrantySelections: { listing_id: string; warranty_plan_id: string }[] =
      [];
    try {
      for (let i = 0; i < sessionStorage.length; i++) {
        const key = sessionStorage.key(i);
        if (key?.startsWith("warranty-selection:")) {
          const listingId = key.replace("warranty-selection:", "");
          const planId = sessionStorage.getItem(key);
          if (planId) {
            warrantySelections.push({
              listing_id: listingId,
              warranty_plan_id: planId,
            });
          }
        }
      }
    } catch {
      // sessionStorage unavailable — proceed without warranty selections
    }

    placeOrder.mutate({
      address_id: Number(selectedAddress.id),
      country_payment_gateway_id: selectedGatewayId,
      idempotency_key: uuidv4(),
      delivery_instruction: selectedInstruction ?? undefined,
      coupon_code: checkoutData?.coupon?.code ?? null,
      wallet_amount_to_use: checkoutData?.wallet_applicable
        ? checkoutData.wallet_balance > 0
          ? checkoutData.wallet_balance
          : null
        : null,
      warranty_selections:
        warrantySelections.length > 0 ? warrantySelections : null,
    });
  };

  const defaultGatewayId = gateways.data?.data?.gateways?.[0]?.id;

  useEffect(() => {
    if (!selectedAddress || !defaultGatewayId) return;
    prepare(Number(selectedAddress.id), defaultGatewayId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedAddress?.id, defaultGatewayId]);

  return {
    createPrepareCheckout: prepareCheckout.mutateAsync,
    checkoutData,
    isPreparingCheckout: prepareCheckout.isPending,
    prepareCheckoutError: prepareCheckout.error,

    addressesData: addresses.data,
    isGettingAddresses: addresses.isPending,
    addressesError: addresses.error,
    selectedAddress,

    gatewaysData: gateways.data?.data,
    isGettingGateways: gateways.isPending,
    gatewaysError: gateways.error,

    handleGatewayChange,
    selectedGatewayId,

    createOrder,
    isCreatingOrder: placeOrder.isPending,
    createOrderError: placeOrder.error,

    selectedInstruction,
    setSelectedInstruction,
  };
};
