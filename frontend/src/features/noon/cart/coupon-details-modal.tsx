"use client";

import { JSXElementConstructor } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { BadgePercentIcon, CheckIcon, CopyIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import toast from "react-hot-toast";
import { ApplicableCoupon } from "@/types/cart.type";
import useLocale from "@/src/hooks/use-locale";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  coupon: ApplicableCoupon;
};

export default function CouponDetailsModal({ trigger, coupon }: Props) {
  const t = useTranslations("cart");
  const locale = useLocale();
  return (
    <Dialog>
      <DialogTrigger render={trigger} />
      <DialogContent className="max-w-2xl gap-0" showCloseButton>
        <DialogHeader className="-mx-4 -mt-4 mb-4 border-b border-border px-6 py-5">
          <DialogTitle className="text-xl font-bold">
            {t("couponDetails")}
          </DialogTitle>
          <DialogDescription className={"flex flex-col gap-3"}>
            {coupon.title[locale]}
            <div className="flex items-center gap-4">
              {/* coupon ticket */}
              <div
                className="py-2 px-3
               leading-4 rounded-md 
               bg-light-green border
                border-green text-green
                 flex gap-2 items-center
                  relative"
              >
                <BadgePercentIcon size={"20px"} />
                <p>{coupon.code}</p>
              </div>
              <Button
                variant={"ghost"}
                className={"text-blue bg-white"}
                onClick={() => {
                  navigator.clipboard.writeText(coupon?.code).then(() => {
                    toast.success(t("couponCopied"));
                  });
                }}
              >
                <CopyIcon /> {t("copyCode")}
              </Button>
            </div>
          </DialogDescription>
        </DialogHeader>
        <div className="max-h-140 overflow-auto">
          <ul className="flex flex-col gap-3">
            {coupon.terms[locale].map((t, i) => (
              <li key={i} className="flex gap-2 items-center">
                <CheckIcon
                  className="text-green"
                  size={"16px"}
                  strokeWidth={"4px"}
                />
                <p className="text-gray">{t}</p>
              </li>
            ))}
          </ul>
        </div>
      </DialogContent>
    </Dialog>
  );
}
