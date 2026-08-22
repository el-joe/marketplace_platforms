"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { ReceiptIcon } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import Price from "@/src/components/shared/Price";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { ApiRequestError } from "@/src/lib/utils";
import { getMyGiftCardPurchases } from "../../api/gift-cards.actions";
import type { GiftCardPurchase } from "../../helpers/types";
import { useAuthContext } from "@/src/providers/auth-provider";

export default function MyGiftCardsDialog() {
  const t = useTranslations("giftCards");
  const { protectedWithAuth } = useAuthContext();

  const [open, setOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [purchases, setPurchases] = useState<GiftCardPurchase[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;

    let cancelled = false;
    setIsLoading(true);
    setError(null);

    getMyGiftCardPurchases()
      .then((response) => {
        if (!cancelled) setPurchases(response.data);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiRequestError ? err.message : t("myGiftCardsLoadFailed"),
          );
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [open, t]);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <Button
        variant="outline"
        className="h-10 gap-1.5 px-3 border border-blue text-blue"
        onClick={() => protectedWithAuth(() => setOpen(true))}
      >
        <ReceiptIcon className="size-4" />
        {t("viewPastOrders")}
      </Button>

      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("myGiftCardsTitle")}</DialogTitle>
        </DialogHeader>

        {isLoading && (
          <p className="py-8 text-center text-sm text-gray">{t("loading")}</p>
        )}

        {!isLoading && error && (
          <p className="py-8 text-center text-sm text-red">{error}</p>
        )}

        {!isLoading && !error && purchases.length === 0 && (
          <p className="py-8 text-center text-sm text-gray">
            {t("myGiftCardsEmptyMessage")}
          </p>
        )}

        {!isLoading && !error && purchases.length > 0 && (
          <div className="flex flex-col divide-y divide-border">
            {purchases.map((purchase) => (
              <div
                key={purchase.id}
                className="flex items-center justify-between py-3"
              >
                <div>
                  <p className="font-medium font-mono">
                    {purchase.gift_card_code ?? t("giftCardPendingDelivery")}
                  </p>
                  <p className="mt-1 text-xs text-gray">
                    {t("giftCardPurchasedOnLabel")}{" "}
                    {purchase.created_at.slice(0, 10)}
                  </p>
                </div>
                <Price
                  currentPrice={Number(purchase.amount_paid)}
                  currency={purchase.currency_code as CurrencyCode}
                  size="sm"
                />
              </div>
            ))}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
