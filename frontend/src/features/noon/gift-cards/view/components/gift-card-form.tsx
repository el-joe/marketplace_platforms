"use client";

import { useTranslations } from "next-intl";
import Card from "@/src/components/shared/Card";
import { useFormActions } from "../../helpers/use-form-actions";
import SelectedThemePreview from "./selected-theme-preview";
import ThemeSelector from "./theme-selector";
import QuantitySelector from "./quantity-selector";
import ReceiverForm from "./receiver-form";
import PriceSummary from "./price-summary";
import type { GiftCardBatch } from "../../helpers/types";

type Props = {
  batch: GiftCardBatch;
};

export default function GiftCardForm({ batch }: Props) {
  const t = useTranslations("giftCards");
  const {
    images,
    selectedThemeIndex,
    setSelectedThemeIndex,
    selectedImage,
    quantity,
    onQuantityChange,
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
  } = useFormActions(batch);

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

          <QuantitySelector
            quantity={quantity}
            min={minQuantity}
            max={maxQuantity}
            onChange={onQuantityChange}
          />

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
