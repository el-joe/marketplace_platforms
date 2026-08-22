"use client";

import { JSXElementConstructor } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { WalletIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";
import { Spinner } from "@/src/components/ui/spinner";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
};

export default function CouponsListModal({ trigger }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();
  const { cart, applyCoupon, isMutating } = useCartContext();
  return (
    <Dialog>
      <DialogTrigger render={trigger} />
      <DialogContent className="max-w-lg gap-0" showCloseButton>
        <DialogHeader className="-mx-4 -mt-4 mb-4 border-b border-border px-6 py-5">
          <DialogTitle className="text-xl font-bold">
            {t("couponDetails")}
          </DialogTitle>
        </DialogHeader>
        <div className="max-h-140 overflow-auto">
          <h4 className="text-lg mb-2 font-semibold">{t("couponOffers")}</h4>
          <div className="flex flex-col gap-3 items-stretch">
            {cart?.cart?.items[0]?.applicable_coupons.map((c, i) => (
              <div key={i} className="border border-border rounded-lg bg-white">
                <div className="flex items-center gap-2 px-3 py-2 bg-light-green text-green">
                  <WalletIcon size={"18px"} />
                  <p>{c.name}</p>
                </div>
                <div className="px-3 py-2">
                  <p>{c.title[locale]}</p>
                  {/* <p className="text-xs text-gray mt-2 mb-3">
                    up to 20 AED on selected items
                  </p> */}
                  <div className="flex justify-between items-center mt-3">
                    <div className="px-3 py-1 text-green text-base border border-green border-dashed rounded-md">
                      {c.code}
                    </div>
                    <Button
                      className={
                        "bg-blue-2 text-white text-base px-4 leading-1"
                      }
                      disabled={isMutating || cart.cart.coupon?.code === c.code}
                      onClick={() => applyCoupon(c.code)}
                    >
                      {isMutating ? <Spinner /> : t("apply")}
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
