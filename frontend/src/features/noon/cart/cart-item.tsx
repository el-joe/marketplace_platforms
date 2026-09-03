import { Link } from "@/i18n/navigation";
import { Counter } from "@/src/components/shared/Counter";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { ShippingGroupItem, ShippingMethod } from "@/types/cart.type";
import { StoreIcon, Trash2Icon, TruckIcon, XIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React, { useEffect, useRef, useState } from "react";
import { Swiper, SwiperSlide } from "swiper/react";
import CouponDetailsModal from "./coupon-details-modal";

type Props = {
  item: ShippingGroupItem;
  shippingMethod: ShippingMethod;
  isFreeShipping: boolean;
};

export default function CartItem({
  item,
  shippingMethod,
  isFreeShipping,
}: Props) {
  const {
    cart,
    updateItemQuantity,
    isMutating,
    removeItem,
    removeItemWarranty,
    targetItemMutating,
  } = useCartContext();
  const t = useTranslations("cart");
  const locale = useLocale();
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [ItemQuantity, setItemQuantity] = useState<number>(item.quantity);
  useEffect(() => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    if (ItemQuantity === item.quantity) return;
    timeoutRef.current = setTimeout(() => {
      updateItemQuantity({
        cartItemId: item.id,
        quantity: ItemQuantity,
      });
    }, 2000);
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ItemQuantity]);
  return (
    <div
      key={item.id}
      className="p-2 lg:p-4 not-last:border-b border-dashed border-border min-h-58.75"
    >
      <div className="flex gap-4">
        {/* item thumbnail + qtu control */}
        <div className="relative w-21 md:w-24 lg:w-28 xl:w-32 h-fit">
          <div className="rounded-[16px] w-full h-fit max-h-48 overflow-hidden">
            <Image
              src={item?.primary_image || "/images/no-image-available-icon.jpg"}
              alt={
                locale === "ar" ? item.product_name_ar : item.product_name_en
              }
              width={160}
              height={280}
              className="object-contain"
            />
          </div>
          <Counter
            className="absolute top-9/10 left-1/2 -translate-x-1/2"
            value={ItemQuantity}
            onChange={(q) => setItemQuantity(q)}
            disabled={isMutating}
            loading={isMutating && targetItemMutating === item.id}
            onDelete={() => removeItem(item.id)}
            max={item?.max_order_quantity || 10}
          />
        </div>
        {/* item body */}
        <div className="flex flex-col gap-2 flex-1">
          <div className="flex items-start gap-3">
            {/* title */}
            <Link href={`/products/${item.listing_id}`} className="flex-1">
              <h3 className="font-semibold flex-1 line-clamp-2">
                {locale === "ar" ? item.product_name_ar : item.product_name_en}
              </h3>
            </Link>
            {/* price */}
            <Price
              currentPrice={item.unit_price}
              variant="cart"
              className="hidden! lg:inline-flex!"
            />
            {/* remove button */}
            <Button
              className={
                "p-2 rounded-md bg-gray-3 text-red border border-border hidden lg:inline-flex"
              }
              onClick={() => removeItem(item.id)}
              disabled={isMutating}
            >
              <Trash2Icon />
            </Button>
          </div>
          {/* variants */}
          <p className="text-xs bg-gray-2 border border-border py-0.5 px-1 rounded-md w-fit line-clamp-1 overflow-auto">
            {item.variant_name}
          </p>
          {/* small screen price */}
          <Price
            currentPrice={item.unit_price}
            // discountPercent={item.discountPercentage}
            // oldPrice={item.oldPrice}
            className="lg:hidden"
          />
          {/* delivery date */}
          <p
            className={`text-sm rounded-md p-1 w-fit text-light`}
            style={{
              background: `linear-gradient(90deg,${shippingMethod?.badge_color_hex}33 0%,var(--color-white) 90%)`,
            }}
          >
            {locale === "ar"
              ? shippingMethod?.delivery_label_ar
              : shippingMethod?.delivery_label_en}
          </p>
          <p className="text-sm text-gray">
            {t("orderIn")}
            18 hrs 12 mins (dummy data)
          </p>
          {/* coupons slides */}
          <Swiper slidesPerView={"auto"} spaceBetween={8} className="w-full">
            {cart?.cart.items
              .find((e) => e.cart_item_id === item.id)
              ?.applicable_coupons?.map((c, i) => (
                <SwiperSlide key={i} className="w-fit!">
                  <CouponDetailsModal
                    coupon={c}
                    trigger={
                      <button className="py-1 px-2 bg-light-green cursor-pointer border border-dashed border-green text-light rounded-md w-fit font-bold text-sm">
                        {c.title[locale]}
                      </button>
                    }
                  />
                </SwiperSlide>
              ))}
          </Swiper>
          {/* features */}
          <div className="flex gap-2 flex-wrap">
            {isFreeShipping && (
              <p className="text-gray text-xs flex gap-1">
                <TruckIcon size={"16px"} />
                <span>Free shipping</span>
              </p>
            )}
            {item.vendor.store_name && (
              <p className="text-gray text-xs flex gap-1">
                <StoreIcon size={"16px"} />
                <span>sold by {item.vendor.store_name}</span>
              </p>
            )}
          </div>
        </div>
      </div>
      {/* warranty plan */}
      {item.warranty_plan && (
        <div className="mt-3 ms-0 lg:ms-36 xl:ms-40">
          <p className="text-xs text-gray mb-1.5 font-medium">
            {t("additionalServices")}
          </p>
          <div className="flex items-center gap-3 border border-border rounded-xl px-3 py-2 bg-gray-2/40">
            {item.warranty_plan.image_url && (
              <Image
                src={item.warranty_plan.image_url}
                alt={item.warranty_plan.name}
                width={40}
                height={40}
                className="object-contain shrink-0"
              />
            )}
            <div className="flex-1 min-w-0">
              <p className="text-xs font-semibold text-primary line-clamp-1">
                {item.warranty_plan.name}
              </p>
              <p className="text-xs text-gray">
                {t("warrantyExtended")} · {item.warranty_plan.duration_label}
              </p>
            </div>
            <Price
              currentPrice={item.warranty_plan.price}
              currency={item.warranty_plan.currency as never}
              size="sm"
              className="shrink-0"
            />
            <button
              onClick={() => removeItemWarranty(item.id)}
              disabled={isMutating}
              className="p-1 rounded-full hover:bg-gray-200 text-gray transition-colors shrink-0"
              aria-label={t("removeWarranty")}
            >
              <XIcon size={14} />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
