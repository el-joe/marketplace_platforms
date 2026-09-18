"use client";

import { useEffect, useState } from "react";
import toast from "react-hot-toast";
import { useTranslations } from "next-intl";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useGiftCardActions } from "./use-gift-card-actions";
import { getPaymentOptions } from "../api/gift-cards.actions";
import type { GiftCardBatch, PaymentOption } from "./types";

/**
 * The gift card purchase FORM only — theme/amount/quantity/receiver state,
 * the payment method (fetched via GET /checkout/payment-options, same as
 * checkout's `useCheckout`, with an offline-gateway proof upload flow), and
 * submit. Any other feature-level action would live in its own
 * `use-*-actions.ts` file instead of being folded in here.
 */
export function useFormActions(batch: GiftCardBatch) {
  const t = useTranslations("giftCards");
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

  const [paymentOptions, setPaymentOptions] = useState<PaymentOption[]>([]);
  const [isLoadingPaymentOptions, setIsLoadingPaymentOptions] = useState(true);
  const [paymentOptionsError, setPaymentOptionsError] = useState<
    string | null
  >(null);
  const [selectedGatewayId, setSelectedGatewayId] = useState("");

  const [offlineProofFile, setOfflineProofFile] = useState<File | null>(null);
  const [offlineProofNote, setOfflineProofNote] = useState("");

  const selectedImage = images[selectedThemeIndex] ?? images[0];

  // Quantity is bound to the batch's own limits, not an arbitrary floor of 1.
  const handleQuantityChange = (value: number) => {
    setQuantity(Math.min(maxQuantity, Math.max(minQuantity, value)));
  };

  const totalAmount = amount * quantity;

  const selectedGateway = paymentOptions.find(
    (o) => o.id === selectedGatewayId,
  );
  const isOfflinePaymentMethod = selectedGateway?.type === "offline";

  const canSubmit =
    totalAmount > 0 &&
    receiverName.trim() !== "" &&
    receiverEmail.trim() !== "" &&
    selectedGatewayId !== "" &&
    (!isOfflinePaymentMethod || !!offlineProofFile) &&
    !isSubmitting;

  useEffect(() => {
    let cancelled = false;

    // eslint-disable-next-line react-hooks/set-state-in-effect -- resets loading/error state for the new fetch kicked off right below
    setIsLoadingPaymentOptions(true);
    setPaymentOptionsError(null);

    getPaymentOptions(totalAmount)
      .then(({ payment_options }) => {
        if (cancelled) return;
        // Gift cards are digital and delivered by email — Cash on Delivery
        // has no physical delivery to attach a COD payment to.
        const payment_options_excl_cod = payment_options.filter(
          (option) => option.gateway_code !== "cod",
        );
        setPaymentOptions(payment_options_excl_cod);

        const available = payment_options_excl_cod.find(
          (option) => option.is_available,
        );
        if (available) {
          setSelectedGatewayId((current) =>
            payment_options_excl_cod.some(
              (o) => o.id === current && o.is_available,
            )
              ? current
              : available.id,
          );
        } else {
          setSelectedGatewayId("");
        }
      })
      .catch(() => {
        if (cancelled) return;
        setPaymentOptions([]);
        setSelectedGatewayId("");
        setPaymentOptionsError(t("noPaymentMethodsAvailable"));
      })
      .finally(() => {
        if (!cancelled) setIsLoadingPaymentOptions(false);
      });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [totalAmount]);

  const handleBuyingForMyselfChange = (value: boolean) => {
    setBuyingForMyself(value);
    setReceiverName(value ? (customer?.name ?? "") : "");
    setReceiverEmail(value ? (customer?.email ?? "") : "");
  };

  const handleSubmit = () => {
    if (isOfflinePaymentMethod && !offlineProofFile) {
      toast.error(t("fileRequiredError"));
      return;
    }

    protectedWithAuth(async () => {
      setIsSubmitting(true);
      try {
        await purchaseGiftCards(
          {
            gift_card_batch_id: batch.id,
            country_payment_gateway_id: selectedGatewayId,
            quantity,
            recipient_name: receiverName,
            recipient_email: receiverEmail,
          },
          isOfflinePaymentMethod
            ? { file: offlineProofFile, note: offlineProofNote }
            : undefined,
        );
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

    isLoadingPaymentOptions,
    paymentOptions,
    paymentOptionsError,
    selectedGatewayId,
    setSelectedGatewayId,
    isOfflinePaymentMethod,

    offlineProofFile,
    setOfflineProofFile,
    offlineProofNote,
    setOfflineProofNote,

    totalAmount,
    canSubmit,
    isSubmitting,
    handleSubmit,
  };
}
