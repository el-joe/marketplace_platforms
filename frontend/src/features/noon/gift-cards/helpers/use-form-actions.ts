"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getPaymentGateways } from "@/src/services/payment-gateways";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useGiftCardActions } from "./use-gift-card-actions";
import type { GiftCardBatch } from "./types";

/**
 * The gift card purchase FORM only — theme/amount/quantity/receiver state,
 * the payment gateway (same pattern as checkout's `useCheckout`: fetch the
 * available gateways, default to the first one), and submit. Any other
 * feature-level action would live in its own `use-*-actions.ts` file instead
 * of being folded in here.
 */
export function useFormActions(batch: GiftCardBatch) {
  const { profile: customer, protectedWithAuth } = useAuthContext();
  const { purchaseGiftCards } = useGiftCardActions();

  // The batch has a single design image; the theme selector still gets an
  // array (it renders a carousel), so it's shown as a one-slide carousel.
  const images = [batch.image_url];

  const { min_quantity: minQuantity, max_quantity: maxQuantity } = batch;

  const [selectedThemeIndex, setSelectedThemeIndex] = useState(0);
  const amount = Number(batch.amount);
  const [quantity, setQuantity] = useState(minQuantity);
  const [buyingForMyself, setBuyingForMyself] = useState(false);
  const [receiverName, setReceiverName] = useState("");
  const [receiverEmail, setReceiverEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { data: gateways } = useQuery({
    queryKey: ["payment-gateways"],
    queryFn: getPaymentGateways,
  });
  // Default to the first available payment gateway, same as checkout.
  const paymentGatewayId = gateways?.[0]?.id;

  const selectedImage = images[selectedThemeIndex] ?? images[0];

  // Quantity is bound to the batch's own limits, not an arbitrary floor of 1.
  const handleQuantityChange = (value: number) => {
    setQuantity(Math.min(maxQuantity, Math.max(minQuantity, value)));
  };

  const totalAmount = amount * quantity;
  const canSubmit =
    totalAmount > 0 &&
    receiverName.trim() !== "" &&
    receiverEmail.trim() !== "" &&
    !!paymentGatewayId &&
    !isSubmitting;

  const handleBuyingForMyselfChange = (value: boolean) => {
    setBuyingForMyself(value);
    setReceiverName(value ? (customer?.name ?? "") : "");
    setReceiverEmail(value ? (customer?.email ?? "") : "");
  };

  const handleSubmit = () => {
    protectedWithAuth(async () => {
      if (!paymentGatewayId) return;
      setIsSubmitting(true);
      try {
        await purchaseGiftCards({
          gift_card_batch_id: batch.id,
          country_payment_gateway_id: paymentGatewayId,
          quantity,
          recipient_name: receiverName,
          recipient_email: receiverEmail,
        });
      } catch {
        // Toasted in the hook — keep the form as-is so the user can retry.
      } finally {
        setIsSubmitting(false);
      }
    });
  };

  return {
    images,
    selectedThemeIndex,
    setSelectedThemeIndex,
    selectedImage,

    amount,

    quantity,
    onQuantityChange: handleQuantityChange,
    minQuantity,
    maxQuantity,

    buyingForMyself,
    receiverName,
    setReceiverName,
    receiverEmail,
    setReceiverEmail,
    handleBuyingForMyselfChange,

    totalAmount,
    canSubmit,
    isSubmitting,
    handleSubmit,
  };
}
