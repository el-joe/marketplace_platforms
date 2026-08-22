"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import type { ReturnReason } from "../../helpers/types";
import { requestReturn } from "../../api/orders.actions";

/**
 * Feature-level action for the cancel-items page — submits a return request
 * (POST /orders/:order_number/returns) for the selected items, since the API
 * has no item-level cancel endpoint (only a whole-order Cancel Order).
 */
export function useCancelActions() {
  const t = useTranslations("profile");
  const router = useRouter();
  const [isCancelling, setIsCancelling] = useState(false);

  const cancelItems = async (
    orderNumber: string,
    orderItemIds: string[],
    reason: ReturnReason,
  ) => {
    setIsCancelling(true);
    try {
      await requestReturn(orderNumber, {
        order_item_ids: orderItemIds,
        reason,
        return_type: "refund",
      });
      toast.success(t("returnRequestSubmitted"));
      router.push(`/orders/${orderNumber}`);
    } catch {
      toast.error(t("returnRequestFailed"));
    } finally {
      setIsCancelling(false);
    }
  };

  return { cancelItems, isCancelling };
}
