"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import { purchaseGiftCard } from "../api/gift-cards.actions";
import type { PurchaseGiftCardPayload } from "./types";

/**
 * Feature-level gift card purchase action. POST /gift-cards/purchase issues one
 * card per call, so buying a `quantity` > 1 loops sequential requests — if one
 * fails partway through, the ones already purchased still stand.
 */
export function useGiftCardActions() {
  const t = useTranslations("giftCards");
  const router = useRouter();

  const purchaseGiftCards = async (
    payload: PurchaseGiftCardPayload,
    quantity: number,
  ) => {
    try {
      for (let i = 0; i < quantity; i += 1) {
        await purchaseGiftCard(payload);
      }
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
