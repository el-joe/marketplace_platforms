"use client";
import { useCartContext } from "@/src/providers/cart-provider";
import CartItem from "./cart-item";
import { ShippingMethod } from "@/types/cart.type";

export default function CartItems() {
  const { cart } = useCartContext();

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
              <CartItem
                key={item.id}
                item={item}
                isFreeShipping={group.is_free_shipping}
                shippingMethod={group?.shipping_method as ShippingMethod}
              />
            ))}
          </div>
        </div>
      ))}
    </>
  );
}
