"use client";
import Price from "@/src/components/shared/Price";
import { Separator } from "@/src/components/ui/separator";
import { ArrowUpRight } from "lucide-react";
import React, { useEffect } from "react";
import { IProductDetails } from "./types";
import formatSecondsToHM from "./helpers/formatSecondsToHM";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import formatTimeRemaining from "./helpers/formatTimeRemaining";
import { useQueryState } from "nuqs";
import { cn } from "@/src/lib/utils";

type Props = {
  deliveryOptions: IProductDetails["delivery_options"];
};

export default function DeliveryInformation({ deliveryOptions }: Props) {
  const t = useTranslations("productView");
  const locale = useLocale();
  const [selectedDelivery, setSelectedDelivery] =
    useQueryState("selectedDelivery");
  useEffect(() => {
    const hasDefault = deliveryOptions.find((o) => o.is_primary);
    if (!selectedDelivery) {
      if (hasDefault) {
        setSelectedDelivery(hasDefault.shipping_method_id);
      } else {
        setSelectedDelivery(deliveryOptions[0]?.shipping_method_id);
      }
    }
  }, [deliveryOptions, selectedDelivery, setSelectedDelivery]);
  return (
    <>
      <h5 className="text-gray font-semibold mb-3">
        {t("deliveryInformation")}
      </h5>
      <div className="flex flex-col">
        {/* delivery bar */}
        {deliveryOptions.map((option, i) => {
          const formattedDeliveryDate = formatTimeRemaining(
            option.estimated_delivery_date.toString(),
            t,
          );
          const isSelected = selectedDelivery === option?.shipping_method_id;
          const lastI = deliveryOptions.length - 1;
          return (
            <div
              key={option?.shipping_method_id}
              className={cn("", isSelected ? " order-1" : " order-2 group")}
              data-last={
                i === lastI ||
                (selectedDelivery ===
                  deliveryOptions[lastI].shipping_method_id &&
                  i === lastI - 1)
              }
            >
              <div
                className={cn(
                  "p-2 flex items-center border border-border gap-2 rounded-md",
                  !isSelected &&
                    " cursor-pointer hover:bg-main bg-light-blue border-light-blue",
                )}
                onClick={() => setSelectedDelivery(option.shipping_method_id)}
              >
                {/* <Image
              src={"/images/express-delivery.svg"}
              alt="delivery-icon"
              width={80}
              height={30}
            /> */}
                <p className="text-sm text-gray-3 px-3 py-1 bg-gray font-bold rounded-lg ">
                  {option.badge_label[locale] || option.name[locale]}
                </p>
                <p>
                  {t("getIt")}{" "}
                  <span className="font-semibold">{formattedDeliveryDate}</span>
                </p>
                {!!option.order_before_seconds && isSelected && (
                  <p className="text-fg-red bg-light-red p-1 text-sm rounded-sm ms-auto">
                    {t("orderIn")}{" "}
                    {formatSecondsToHM(option.order_before_seconds)}
                  </p>
                )}
                {!isSelected &&
                  (option.is_free ? (
                    <span className="ms-auto flex items-center font-bold">
                      {t("freeShipping")}
                      <ArrowUpRight />
                    </span>
                  ) : (
                    <span className="ms-auto flex items-center">
                      <Price
                        currentPrice={option?.shipping_fee}
                      />
                      <ArrowUpRight />
                    </span>
                  ))}
              </div>
              {/* or separator */}
              <div className="flex items-center max-w-xs gap-2 mx-auto my-3 group-[[data-last=true]]:hidden!">
                <Separator className={"flex-1"} />
                <span className="text-sm">{t("or")}</span>
                <Separator className={"flex-1"} />
              </div>
            </div>
          );
        })}
      </div>

      {/* delivery bar */}
      {/* <div className="p-2 flex items-center border border-light-blue gap-2 rounded-md bg-light-blue group hover:text-white">
        <Image
          src={"/images/supermall-delivery.svg"}
          alt="delivery-icon"
          width={80}
          height={30}
        />
        <p>
          {t("getIt")}{" "}
          <span className="font-bold group-hover:text-white text-blue">
            Tomorrow
          </span>
        </p>
        <span className="ms-auto flex items-center">
          <Price currentPrice={92} />
          <ArrowUpRight />
        </span>
      </div> */}
    </>
  );
}
