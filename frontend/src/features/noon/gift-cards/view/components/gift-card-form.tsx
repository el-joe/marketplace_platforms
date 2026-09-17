"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import Card from "@/src/components/shared/Card";
import { useGiftCardActions } from "../../helpers/use-gift-card-actions";
import { getPaymentOptions } from "../../api/gift-cards.actions";
import SelectedThemePreview from "./selected-theme-preview";
import ThemeSelector from "./theme-selector";
import AmountSelector from "./amount-selector";
import QuantitySelector from "./quantity-selector";
import ReceiverForm from "./receiver-form";
import PaymentMethodSelector from "./payment-method-selector";
import PriceSummary from "./price-summary";
import type { GiftCardBatch, PaymentOption } from "../../helpers/types";
import { useAuthContext } from "@/src/providers/auth-provider";

type Props = {
  batch: GiftCardBatch;
  /** Every batch currently sold in this currency — each is a selectable denomination. */
  availableBatches: GiftCardBatch[];
};

export default function GiftCardForm({ batch, availableBatches }: Props) {
  const t = useTranslations("giftCards");
  const { profile: customer, protectedWithAuth } = useAuthContext();
  const { purchaseGiftCards } = useGiftCardActions();

  const amounts = Array.from(
    new Set(availableBatches.map((b) => Number(b.amount))),
  ).sort((a, b) => a - b);

  // The batch has a single design image; the theme selector still gets an
  // array (it renders a carousel), so it's shown as a one-slide carousel.
  const images = [batch.image_url];

  const [selectedThemeIndex, setSelectedThemeIndex] = useState(0);
  const [amount, setAmount] = useState<number>(Number(batch.amount));
  const [quantity, setQuantity] = useState(1);
  const [buyingForMyself, setBuyingForMyself] = useState(false);
  const [receiverName, setReceiverName] = useState("");
  const [receiverEmail, setReceiverEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [paymentOptions, setPaymentOptions] = useState<PaymentOption[]>([]);
  const [isLoadingPaymentOptions, setIsLoadingPaymentOptions] = useState(true);
  const [paymentOptionsError, setPaymentOptionsError] = useState<string | null>(
    null,
  );
  const [selectedGatewayId, setSelectedGatewayId] = useState("");

  const selectedImage = images[selectedThemeIndex] ?? images[0];

  const totalAmount = amount * quantity;
  const canSubmit =
    totalAmount > 0 &&
    receiverName.trim() !== "" &&
    receiverEmail.trim() !== "" &&
    selectedGatewayId !== "" &&
    !isSubmitting;

  useEffect(() => {
    let cancelled = false;

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

  // The amount selector switches between sibling batches (same theme/currency,
  // different denomination); fall back to the originally opened batch if the
  // selected amount has no match (e.g. it just sold out).
  const selectedBatchId =
    availableBatches.find((b) => Number(b.amount) === amount)?.id ?? batch.id;

  const handleSubmit = () => {
    protectedWithAuth(async () => {
      setIsSubmitting(true);
      try {
        await purchaseGiftCards({
          gift_card_batch_id: selectedBatchId,
          country_payment_gateway_id: selectedGatewayId,
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

  return (
    <div className="flex flex-col lg:flex-row gap-8 mt-6">
      <SelectedThemePreview image={selectedImage} />

      <div className="hidden lg:block w-px bg-border self-stretch" />

      <div className="flex-1">
        <h2 className="font-bold text-lg mb-3">{t("giftCardDetails")}</h2>
        <Card className="flex flex-col gap-6">
          <ThemeSelector
            images={images}
            selectedIndex={selectedThemeIndex}
            onSelect={setSelectedThemeIndex}
          />

          <AmountSelector amount={amount} amounts={amounts} onSelect={setAmount} />

          <QuantitySelector quantity={quantity} onChange={setQuantity} />

          <ReceiverForm
            buyingForMyself={buyingForMyself}
            onBuyingForMyselfChange={handleBuyingForMyselfChange}
            receiverName={receiverName}
            onReceiverNameChange={setReceiverName}
            receiverEmail={receiverEmail}
            onReceiverEmailChange={setReceiverEmail}
          />

          <PaymentMethodSelector
            isLoading={isLoadingPaymentOptions}
            options={paymentOptions}
            selectedId={selectedGatewayId}
            onSelect={setSelectedGatewayId}
            error={paymentOptionsError ?? undefined}
          />

          <PriceSummary
            quantity={quantity}
            totalAmount={totalAmount}
            disabled={!canSubmit}
            isSubmitting={isSubmitting}
            onSubmit={handleSubmit}
          />
        </Card>
      </div>
    </div>
  );
}
