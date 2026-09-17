import React, { useMemo, useState } from "react";
import { Button } from "../ui/button";
import { useCartContext } from "@/src/providers/cart-provider";
import { Spinner } from "../ui/spinner";
import { PlusIcon } from "lucide-react";
import Image from "next/image";
import { cn } from "@/src/lib/utils";
import { Counter } from "./Counter";
import CustomAttributesModal, {
  CustomAttributeDefinition,
} from "./custom-attributes-modal";

type Props = {
  listingId: string;
  listingType?: string;
  size?: "sm" | "base" | "lg";
  hasCustomAttributes?: boolean;
  customAttributes?: CustomAttributeDefinition[];
};

const sizes: Record<NonNullable<Props["size"]>, string> = {
  sm: "p-1 w-7 lg:w-9 h-7 lg:h-9",
  base: "p-2  w-7 md:w-8 lg:w-9 h-7 md:h-8 lg:h-9",
  lg: "",
};

export default function AddToCartButton({
  listingId,
  listingType = "vendor",
  size = "base",
  hasCustomAttributes = false,
  customAttributes = [],
}: Props) {
  const {
    cart,
    addItem,
    isMutating,
    targetItemMutating,
    updateItemQuantity,
    removeItem,
  } = useCartContext();
  const [showCustomAttributesModal, setShowCustomAttributesModal] =
    useState(false);

  const cartItem = useMemo(
    () => cart?.cart?.items.find((item) => item.listing_id === listingId),
    [cart, listingId],
  );

  const isThisItemMutating =
    isMutating &&
    (targetItemMutating === listingId ||
      targetItemMutating === cartItem?.cart_item_id);

  return (
    <>
      <Button
        variant={"outline"}
        className={cn(
          "absolute bottom-1 lg:bottom-2 right-2 z-10 min-w-0! min-h-0! bg-gray-2 hover:bg-gray-2 group/cart hover:px-1",
          cartItem &&
            "bg-blue-2 text-white hover:bg-blue-2 hover:text-white border-0 pt-2! hover:w-28",
          sizes[size],
        )}
        disabled={isThisItemMutating}
        onClick={(e) => {
          e.preventDefault();
          if (cartItem) return;
          if (hasCustomAttributes) {
            setShowCustomAttributesModal(true);
            return;
          }
          addItem({ quantity: 1, vendorListingId: listingId, listingType });
        }}
      >
        {isThisItemMutating ? (
          <Spinner />
        ) : cartItem ? (
          <>
            <span className="relative w-6.5 group-hover/cart:hidden">
              <span className="absolute -top-1.5 left-1/2 translate-x-[-35%] text-sm">
                {cartItem.quantity}
              </span>
              <Image src="/images/cart.svg" alt="" width={26} height={26} />
            </span>
            <Counter
              value={cartItem.quantity}
              onChange={(q) =>
                updateItemQuantity({
                  cartItemId: cartItem.cart_item_id,
                  quantity: q,
                })
              }
              disabled={isMutating}
              loading={isThisItemMutating}
              onDelete={() => removeItem(cartItem.cart_item_id)}
              max={cartItem.max_order_quantity}
              className="bg-blue-2 border-0 hidden group-hover/cart:flex! overflow-hidden max-h-6.5"
            />
          </>
        ) : (
          <PlusIcon className="size-4 lg:size-6" />
        )}
      </Button>
      {hasCustomAttributes && (
        <CustomAttributesModal
          open={showCustomAttributesModal}
          attributes={customAttributes}
          onClose={() => setShowCustomAttributesModal(false)}
          isSubmitting={isThisItemMutating}
          onSubmit={(customAttributeValues) => {
            addItem({
              quantity: 1,
              vendorListingId: listingId,
              listingType,
              customAttributeValues,
            }).then(() => setShowCustomAttributesModal(false));
          }}
        />
      )}
    </>
  );
}
