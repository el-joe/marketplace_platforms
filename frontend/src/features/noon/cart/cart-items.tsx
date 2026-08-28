"use client";
import { Link } from "@/i18n/navigation";
import { Counter } from "@/src/components/shared/Counter";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { StoreIcon, Trash2Icon, TruckIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";
import CouponDetailsModal from "./coupon-details-modal";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";

export default function CartItems() {
  const {
    cart,
    updateItemQuantity,
    isMutating,
    removeItem,
    targetItemMutating,
  } = useCartContext();
  const t = useTranslations("cart");
  const locale = useLocale();
  return (
    <>
      {cart?.shipping_groups.map((group) => (
        <div
          className="relative rounded-[16px] overflow-hidden"
          key={group.shipping_method?.id}
        >
          <div
            className="z-0 absolute inset-x-0 top-0 h-17 bg-white "
            style={{
              background: `linear-gradient(90deg,${group?.shipping_method?.badge_color_hex} 0%,${group?.shipping_method?.badge_color_hex}33 10%,var(--color-white) 100%)`,
            }}
          >
            <div
              className={`ps-4 pe-8 py-2 w-fit text-white font-black rounded-se-[40px] corner-se-bevel`}
              style={{ background: group.shipping_method?.badge_color_hex }}
            >
              {group?.shipping_method?.badge_label_en ||
                group?.shipping_method?.name}
            </div>
          </div>
          <div
            className="flex flex-col gap-0 rounded-[16px] bg-white mt-6 lg:mt-7 xl:mt-10 z-1 relative border "
            style={{ borderColor: group.shipping_method?.badge_color_hex }}
          >
            {group.items?.map((item) => (
              <div
                key={item.id}
                className="p-2 lg:p-4 not-last:border-b border-dashed border-border min-h-58.75"
              >
                <div className="flex gap-4">
                  {/* item thumbnail + qtu control */}
                  <div className="relative w-21 md:w-24 lg:w-28 xl:w-32 h-fit">
                    <div className="rounded-[16px] w-full h-fit max-h-48 overflow-hidden">
                      <Image
                        src={item.primary_image as string}
                        alt={
                          locale === "ar"
                            ? item.product_name_ar
                            : item.product_name_en
                        }
                        width={160}
                        height={280}
                        className="object-contain"
                      />
                    </div>
                    <Counter
                      className="absolute top-9/10 left-1/2 -translate-x-1/2"
                      value={item.quantity}
                      onChange={(q) =>
                        updateItemQuantity({
                          cartItemId: item.id,
                          quantity: q,
                        })
                      }
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
                      {/* <Link href={`/products/${item.slug}`} className="flex-1"> */}
                      <Link
                        href={`/products/${item.listing_id}`}
                        className="flex-1"
                      >
                        <h3 className="font-semibold flex-1 line-clamp-2">
                          {locale === "ar"
                            ? item.product_name_ar
                            : item.product_name_en}
                        </h3>
                      </Link>
                      {/* price */}
                      <Price
                        currentPrice={item.unit_price}
                        // discountPercent={item.discountPercentage}
                        // oldPrice={item.oldPrice}
                        // size="lg"
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
                        background: `linear-gradient(90deg,${group?.shipping_method?.badge_color_hex}33 0%,var(--color-white) 90%)`,
                      }}
                    >
                      {locale === "ar"
                        ? group.shipping_method?.delivery_label_ar
                        : group.shipping_method?.delivery_label_en}
                    </p>
                    <p className="text-sm text-gray">
                      {t("orderIn")}
                      18 hrs 12 mins
                    </p>
                    {/* coupons slides */}
                    <Swiper
                      slidesPerView={"auto"}
                      spaceBetween={8}
                      className="w-full"
                    >
                      {cart.cart.items
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
                      {group.is_free_shipping && (
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
                {/* extra warranty */}
              </div>
            ))}
          </div>
        </div>
      ))}
    </>
  );
}
