"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import Card from "@/src/components/shared/Card";
import { useGiftCardActions } from "../../helpers/use-gift-card-actions";
import SelectedThemePreview from "./selected-theme-preview";
import ThemeSelector from "./theme-selector";
import AmountSelector from "./amount-selector";
import QuantitySelector from "./quantity-selector";
import ReceiverForm from "./receiver-form";
import PriceSummary from "./price-summary";
import type { GiftCardCategory } from "../../data";
import { giftCardAmounts } from "../../data";
import { useAuthContext } from "@/src/providers/auth-provider";

type Props = {
  category: GiftCardCategory;
};

export default function GiftCardForm({ category }: Props) {
  const t = useTranslations("giftCards");
  const { profile: customer, protectedWithAuth } = useAuthContext();
  const { purchaseGiftCards } = useGiftCardActions();

  const [selectedThemeIndex, setSelectedThemeIndex] = useState(0);
  const [amount, setAmount] = useState<number>(giftCardAmounts[0]);
  const [quantity, setQuantity] = useState(1);
  const [buyingForMyself, setBuyingForMyself] = useState(false);
  const [receiverName, setReceiverName] = useState("");
  const [receiverEmail, setReceiverEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const selectedImage =
    category.images[selectedThemeIndex] ?? category.images[0];

  const totalAmount = amount * quantity;
  const canSubmit =
    totalAmount > 0 &&
    receiverName.trim() !== "" &&
    receiverEmail.trim() !== "" &&
    !isSubmitting;

  const handleBuyingForMyselfChange = (value: boolean) => {
    setBuyingForMyself(value);
    setReceiverName(value ? (customer?.name ?? "") : "");
    setReceiverEmail(value ? (customer?.email ?? "") : "");
  };

  const handleSubmit = () => {
    protectedWithAuth(async () => {
      setIsSubmitting(true);
      try {
        await purchaseGiftCards(
          {
            denomination_cents: Math.round(amount * 100),
            currency: "AED",
            recipient_name: receiverName,
            recipient_email: receiverEmail,
          },
          quantity,
        );
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
            images={category.images}
            selectedIndex={selectedThemeIndex}
            onSelect={setSelectedThemeIndex}
          />

          <AmountSelector amount={amount} onSelect={setAmount} />

          <QuantitySelector quantity={quantity} onChange={setQuantity} />

          <ReceiverForm
            buyingForMyself={buyingForMyself}
            onBuyingForMyselfChange={handleBuyingForMyselfChange}
            receiverName={receiverName}
            onReceiverNameChange={setReceiverName}
            receiverEmail={receiverEmail}
            onReceiverEmailChange={setReceiverEmail}
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
