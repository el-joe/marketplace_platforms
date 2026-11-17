"use client";

import { JSXElementConstructor } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import {
  BadgePercentIcon,
  CheckIcon,
  ClockIcon,
  TagIcon,
} from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";
import { Spinner } from "@/src/components/ui/spinner";
import CurrencySymbol from "@/src/components/shared/CurrencySymbol";
import { cn } from "@/src/lib/utils";
import { getAvailableCoupons } from "./helpers/get-available-coupons";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
};

export default function CouponsListModal({ trigger }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const { cart, applyCoupon, isMutating, targetCoupon } = useCartContext();
  const coupons = getAvailableCoupons(cart?.cart?.items);
  const appliedCode = cart?.cart?.coupon?.code;
  const currency = cart?.cart?.currency;
  const dateFormatter = new Intl.DateTimeFormat(
    locale === "ar" ? "ar-EG" : "en-GB",
    { day: "numeric", month: "short", year: "numeric" },
  );

  return (
    <Dialog>
      {/* The trigger is a <div> row, not a native <button>. */}
      <DialogTrigger render={trigger} nativeButton={false} />
      <DialogContent
        // Bottom sheet on mobile, centered dialog from md up.
        className="max-w-lg gap-0 p-0 max-md:top-auto max-md:bottom-0 max-md:translate-y-0 max-md:max-w-full max-md:rounded-b-none max-md:rounded-t-3xl max-md:data-open:slide-in-from-bottom-10 max-md:data-open:zoom-in-100"
        showCloseButton
      >
        <div className="mx-auto mt-2 h-1 w-10 rounded-full bg-border-color md:hidden" />
        <DialogHeader className="flex-row items-center gap-3 border-b border-border px-4 pt-3 pb-3 pe-14 md:pt-5 md:px-6">
          <div className="grid size-9 shrink-0 place-items-center rounded-full bg-light-blue text-blue">
            <BadgePercentIcon size={19} />
          </div>
          <div className="min-w-0 text-start">
            <DialogTitle className="text-lg font-bold">
              {t("availableOffers")}
            </DialogTitle>
            {coupons.length > 0 && (
              <DialogDescription className="text-xs text-gray">
                {t("offersCount", { count: coupons.length })}
              </DialogDescription>
            )}
          </div>
        </DialogHeader>

        <div className="max-h-[65vh] overflow-auto px-4 pt-4 pb-6 md:px-6">
          {coupons.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-6 text-center">
              <div className="mb-1 grid size-16 place-items-center rounded-full bg-light-blue text-blue">
                <TagIcon size={30} />
              </div>
              <p className="text-base font-bold">{t("noOffersTitle")}</p>
              <p className="max-w-xs text-sm text-gray">
                {t("noOffersDescription")}
              </p>
              <DialogClose
                render={
                  <Button className="mt-4 h-11 w-full rounded-xl bg-blue text-base font-bold text-white" />
                }
              >
                {t("gotIt")}
              </DialogClose>
            </div>
          ) : (
            <ul className="flex flex-col gap-3">
              {coupons.map((coupon) => {
                const isApplied = appliedCode === coupon.code;
                const isApplying = isMutating && targetCoupon === coupon.code;
                const validUntil = coupon.valid_until
                  ? dateFormatter.format(new Date(coupon.valid_until))
                  : null;
                return (
                  <li
                    key={coupon.code}
                    className={cn(
                      "overflow-hidden rounded-[14px] border bg-white",
                      isApplied ? "border-green" : "border-border",
                    )}
                  >
                    <div className="flex items-center gap-2 bg-light-green px-3.5 py-2 text-green">
                      <BadgePercentIcon size={18} className="shrink-0" />
                      <p className="min-w-0 flex-1 truncate text-sm font-bold">
                        {/* `name` is the admin's internal name — never show it. */}
                        {coupon.is_bank_offer && coupon.bank_name
                          ? coupon.bank_name
                          : t("couponOffer")}
                      </p>
                      {coupon.estimated_saving > 0 && currency && (
                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-green px-2 py-0.5 text-[11px] font-bold text-white">
                          {t.rich("saveAmount", {
                            amount: () => (
                              <span className="inline-flex items-center gap-0.5">
                                <CurrencySymbol
                                  code={currency}
                                  imageClassName="size-2.5 brightness-0 invert"
                                />
                                {coupon.estimated_saving}
                              </span>
                            ),
                          })}
                        </span>
                      )}
                    </div>
                    <div className="flex flex-col gap-2.5 px-3.5 pt-3 pb-3.5">
                      <p className="text-[15px] font-semibold leading-snug">
                        {coupon.title?.[locale] ||
                          coupon.title?.en ||
                          coupon.name}
                      </p>
                      {validUntil && (
                        <p className="flex items-center gap-1 text-xs text-gray">
                          <ClockIcon size={13} />
                          {t("validUntil", { date: validUntil })}
                        </p>
                      )}
                      <div className="border-t border-dashed border-border" />
                      <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-1.5 rounded-lg border border-dashed border-green bg-light-green px-2.5 py-1 text-green">
                          <TagIcon size={14} />
                          <span className="text-sm font-bold tracking-wider">
                            {coupon.code}
                          </span>
                        </div>
                        <Button
                          className={cn(
                            "h-9 min-w-24 rounded-[10px] px-5 text-sm font-bold",
                            isApplied
                              ? "bg-light-green text-green disabled:opacity-100"
                              : "bg-blue text-white",
                          )}
                          disabled={isApplied || (isMutating && !isApplying)}
                          onClick={() => {
                            if (isMutating || isApplied) return;
                            applyCoupon(coupon.code).catch(() => {
                              // onError in use-cart.ts already surfaces a toast.
                            });
                          }}
                        >
                          {isApplying ? (
                            <Spinner />
                          ) : isApplied ? (
                            <>
                              <CheckIcon size={15} strokeWidth={3} />
                              {t("applied")}
                            </>
                          ) : (
                            t("apply")
                          )}
                        </Button>
                      </div>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
