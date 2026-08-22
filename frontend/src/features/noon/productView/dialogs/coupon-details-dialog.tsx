"use client";
import {
  Dialog,
  DialogContent,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import React, { JSXElementConstructor } from "react";
import { IProductDetails } from "../types";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import toast from "react-hot-toast";
import { Link } from "@/i18n/navigation";
import { Separator } from "@/src/components/ui/separator";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  coupon: IProductDetails["coupons"][0];
};

export default function CouponDetailsDialog({ trigger, coupon }: Props) {
  const locale = useLocale();
  const t = useTranslations("productView");
  return (
    <Dialog>
      <DialogTrigger render={trigger} />
      <DialogContent className={"max-w-xl pt-12"}>
        <h3 className="text-center text-lg font-semibold">
          {coupon.title[locale]}
          <span className="block">
            {t("code")}: {coupon.code}
          </span>
        </h3>
        <div className="flex items-center px-3 py-2 mx-auto border rounded-lg border-green min-w-7/12 gap-2 bg-linear-to-r from-35% from-[#fff] to-100% to-[#effdf2]">
          <p className="flex-1 text-center font-bold">{coupon.code}</p>
          <Button
            className={"bg-blue-2 text-white px-4"}
            onClick={() => {
              navigator.clipboard.writeText(coupon.code).then(() => {
                toast.success(t("couponCopied"));
              });
            }}
          >
            {t("copy")}
          </Button>
        </div>
        <Link href={"#"} className="text-center text-blue-2">
          {t("viewProducts")}
        </Link>
        <div className="flex items-center gap-1">
          <Separator className="flex-1" />
          <div className="uppercase text-gray">{t("or")}</div>
          <Separator className="flex-1" />
        </div>
        <h3 className="text-base font-semibold uppercase">
          {t("couponDetails")}
        </h3>
        <ul className="list-disc ps-6">
          {(coupon.terms[locale] as string[])?.map((e, i) => (
            <li key={i}>
              <span className="text-gray">{e}</span>
            </li>
          ))}
        </ul>
      </DialogContent>
    </Dialog>
  );
}
