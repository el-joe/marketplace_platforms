"use client";
import { Counter } from "@/src/components/shared/Counter";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import { Spinner } from "@/src/components/ui/spinner";
import { useCartContext } from "@/src/providers/cart-provider";
import { useTranslations } from "next-intl";
import { useQueryState } from "nuqs";
import React from "react";
import { useWarrantySelection } from "./warranty-selection-context";

type Props = {
  listingId: string;
  quantity?: number;
};

export default function CartButton({ listingId, quantity = 1 }: Props) {
  const t = useTranslations("productView");
  const {
    addItem,
    isMutating,
    cart,
    isLoading,
    updateItemQuantity,
    removeItem,
    targetItemMutating,
  } = useCartContext();
  const [selectedDelivery] = useQueryState("selectedDelivery");
  const { selectedPlanId, clearSelection } = useWarrantySelection();
  return (
    <>
      {isLoading ? (
        <Skeleton className="w-full h-11" />
      ) : cart?.cart.items.find((item) => item.listing_id === listingId) ? (
        <Counter
          className="w-full bg-blue text-white min-h-11 text-lg"
          loading={isMutating}
          value={
            cart?.cart?.items.find((item) => item.listing_id === listingId)
              ?.quantity as number
          }
          onChange={(q) =>
            updateItemQuantity({
              cartItemId: cart?.cart?.items.find(
                (item) => item.listing_id === listingId,
              )?.cart_item_id as string,
              quantity: q,
            })
          }
          disabled={isMutating}
          onDelete={() =>
            removeItem(
              cart?.cart?.items.find((item) => item.listing_id === listingId)
                ?.cart_item_id as string,
            )
          }
          max={
            cart?.cart.items.find((item) => item.listing_id === listingId)
              ?.max_order_quantity
          }
        />
      ) : (
        <Button
          onClick={() =>
            addItem({
              vendorListingId: listingId,
              quantity,
              shippingMethodId: selectedDelivery as string,
              warrantyPlanId: selectedPlanId,
            }).then(() => {
              clearSelection();
            })
          }
          disabled={isMutating}
          size={"lg"}
          className={
            "mx-auto bg-blue text-white min-h-11! text-lg w-full py-2 rounded-xl uppercase justify-center"
          }
        >
          {isMutating && (targetItemMutating as string) === listingId ? (
            <Spinner />
          ) : (
            t("addToCart")
          )}
        </Button>
      )}
    </>
  );
}
