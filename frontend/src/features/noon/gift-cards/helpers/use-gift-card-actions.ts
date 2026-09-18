"use client";

import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import { ApiRequestError } from "@/src/lib/utils";
import { purchaseGiftCard, uploadGiftCardPaymentProof } from "../api/gift-cards.actions";
import type { PurchaseGiftCardPayload } from "./types";

/**
 * Feature-level gift card purchase action. POST /gift-card-store/purchase takes
 * `quantity` in the payload, so buying multiple cards is a single request.
 *
 * When the selected gateway is offline (e.g. bank transfer), the caller passes
 * the collected proof file + optional note, and it's uploaded immediately
 * after the order is created — mirroring checkout's use-checkout.ts pattern
 * (Task 04). Delivery of the gift card code is held back on the backend
 * until an admin approves the offline payment, so it's safe to still route
 * to `/gift-cards` on success even though delivery hasn't happened yet.
 */
export function useGiftCardActions() {
  const t = useTranslations("giftCards");
  const router = useRouter();

  const purchaseGiftCards = async (
    payload: PurchaseGiftCardPayload,
    proof?: { file: File | null; note: string },
  ) => {
    try {
      const result = await purchaseGiftCard(payload);

      if (proof?.file && result.order_number) {
        try {
          await uploadGiftCardPaymentProof(result.order_number, proof.file, proof.note);
        } catch (uploadError) {
          // Non-fatal: the gift card purchase already went through. Surface a
          // warning but don't treat the whole purchase as failed.
          console.error("Failed to upload gift card payment proof:", uploadError);
          toast.error(t("proofUploadFailedFallback"));
        }
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
