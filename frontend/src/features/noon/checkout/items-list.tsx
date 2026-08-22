import Price from "@/src/components/shared/Price";
import Image from "next/image";
import React from "react";
import { IPrepareCheckout } from "./types/checkout.type";
import { centsToAmount } from "@/src/lib/utils";
import { useTranslations } from "next-intl";
import { Swiper, SwiperSlide } from "swiper/react";
import useLocale from "@/src/hooks/use-locale";

export default function ItemsList({
  shipment_groups,
}: {
  shipment_groups: IPrepareCheckout["shipment_groups"];
}) {
  return (
    <div className="flex flex-col gap-4">
      {shipment_groups.map((group, index) => (
        <Shipment
          key={group.shipping_method.id}
          group={group}
          index={index + 1}
        />
      ))}
    </div>
  );
}

const Shipment = ({
  group,
  index,
}: {
  group: IPrepareCheckout["shipment_groups"][0];
  index: number;
}) => {
  const t = useTranslations("checkout");
  const locale = useLocale();
  return (
    <div className="bg-white rounded-2xl">
      <div className="flex gap-2 items-center p-3">
        <h3 className="text-base font-semibold">
          {t("shipment")} {index}
        </h3>
        <p className="py-1 px-2 rounded-full bg-gray-2 text-gray text-xs">
          {group?.items_count} {t("items")}
        </p>
      </div>
      <Swiper>
        {group.items.map((item) => (
          <SwiperSlide key={item.listing_id}>
            {" "}
            <div className="flex items-start gap-2 py-5">
              {/* item thumbnail + qtu control */}
              <div className="relative w-21 md:w-24 lg:w-28 xl:w-32 h-fit">
                <div className="rounded-[16px] w-full h-fit max-h-48 overflow-hidden">
                  <Image
                    src={item.primary_image || ""}
                    alt={
                      locale === "ar"
                        ? item.product_name_ar
                        : item.product_name_en
                    }
                    width={160}
                    height={280}
                    className="object-contain"
                  />
                  <div className="absolute bottom-1 right-1 text-xs text-gray font-bold border border-border p-1 rounded-md bg-white grid place-items-center">
                    x{item.quantity}
                  </div>
                </div>
              </div>
              <div className="flex-1">
                <h4 className="text-light mb-2 text-sm md:text-base">
                  {locale === "ar"
                    ? item.product_name_ar
                    : item.product_name_en}
                </h4>
                <Price currentPrice={centsToAmount(item.unit_price)} />
              </div>
            </div>
          </SwiperSlide>
        ))}
      </Swiper>

      <div className="flex justify-between items-center h-12">
        <div className="h-12 ps-4 pe-10 relative text-sm/[42px] z-1 ">
          <div className="absolute inset-0 -z-10 bg-border-strip"></div>
          {t("getItBy")}
          <span className="font-bold"> no estimated delivery date</span>
        </div>
        <div className="p-3">
          <p
            style={{ backgroundColor: group.shipping_method.badge_color_hex }}
            className="text-sm font-bold text-white px-3 py-1 rounded-full"
          >
            {locale === "ar"
              ? group.shipping_method.badge_label_ar
              : group.shipping_method.badge_label_en}
          </p>
        </div>
      </div>
    </div>
  );
};
