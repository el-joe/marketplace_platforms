import type { IPrepareCheckout } from "../types/checkout.type";

/**
 * Wallet amount applied to this checkout — the single source for both the
 * payment summary and the place-order payload (R1: the payload used to send
 * the whole balance, e.g. 20 for a 15.75 order, and the API rejected it).
 *
 * COD orders must be paid fully by wallet or not use it at all, so a balance
 * below the total is not applied to a COD order.
 */
export function resolveWalletDeduction(
  checkoutData: IPrepareCheckout | undefined | null,
): number {
  if (!checkoutData?.wallet_applicable) return 0;

  const balance = Number(checkoutData.wallet_balance) || 0;
  const total = Number(checkoutData.order_summary?.total) || 0;
  if (balance <= 0 || total <= 0) return 0;

  if (checkoutData.gateway_code === "cod" && balance < total) return 0;

  return Math.round(Math.min(balance, total) * 10000) / 10000;
}
