"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import { purchaseGiftCard } from "../api/gift-cards.actions";
import type { PurchaseGiftCardPayload } from "./types";

/**
 * Feature-level gift card purchase action. POST /gift-card-store/purchase takes
 * `quantity` in the payload, so buying multiple cards is a single request.
 */
export function useGiftCardActions() {
  const t = useTranslations("giftCards");
  const router = useRouter();

  const purchaseGiftCards = async (payload: PurchaseGiftCardPayload) => {
    try {
      await purchaseGiftCard(payload);
      toast.success(t("giftCardPurchased"));
      router.push("/gift-cards");
    } catch (error) {
      const message =
        error instanceof ApiRequestError ? error.message : t("giftCardPurchaseFailed");
      toast.error(message);
      throw error;
    }
  };

  return { purchaseGiftCards };
}
