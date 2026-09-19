"use client";
import { Counter } from "@/src/components/shared/Counter";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import { Spinner } from "@/src/components/ui/spinner";
import { useCartContext } from "@/src/providers/cart-provider";
import { useTranslations } from "next-intl";
import { useQueryState } from "nuqs";
import React, { useState } from "react";
import { useWarrantySelection } from "./warranty-selection-context";
import CustomAttributesModal from "@/src/components/shared/custom-attributes-modal";
import { ProductCustomAttribute } from "./types/product-details";
import { cn } from "@/src/lib/utils";

type Props = {
  listingId: string;
  quantity?: number;
  hasCustomAttributes?: boolean;
  customAttributes?: ProductCustomAttribute[];
  classes?: string;
};

export default function CartButton({
  listingId,
  quantity = 1,
  hasCustomAttributes = false,
  customAttributes = [],
  classes,
}: Props) {
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
  const { selectedPlanId, clearSelection, handleProductAddedToCart } =
    useWarrantySelection();
  const [showCustomAttributesModal, setShowCustomAttributesModal] =
    useState(false);

  const performAddToCart = (
    customAttributeValues?: {
      product_custom_attribute_id: string;
      value: string;
    }[],
  ) => {
    const hadWarrantySelected = Boolean(selectedPlanId);
    return addItem({
      vendorListingId: listingId,
      quantity,
      shippingMethodId: selectedDelivery as string,
      warrantyPlanId: selectedPlanId,
      customAttributeValues,
    }).then((cartData) => {
      clearSelection();
      handleProductAddedToCart?.(
        hadWarrantySelected,
        cartData?.data?.item?.cart_item_id,
      );
    });
  };

  return (
    <>
      {hasCustomAttributes && (
        <CustomAttributesModal
          open={showCustomAttributesModal}
          attributes={customAttributes}
          onClose={() => setShowCustomAttributesModal(false)}
          isSubmitting={isMutating}
          onSubmit={(values) => {
            performAddToCart(values)
              .then(() => setShowCustomAttributesModal(false))
              .catch(() => {
                // onError in use-cart.ts already surfaces a toast; keep the
                // modal open so the user can retry instead of losing input.
              });
          }}
        />
      )}
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
          onClick={() => {
            if (hasCustomAttributes) {
              setShowCustomAttributesModal(true);
              return;
            }
            performAddToCart().catch(() => {
              // onError in use-cart.ts already surfaces a toast; swallow
              // here so the rejection doesn't also bubble up as an
              // uncaught promise.
            });
          }}
          disabled={isMutating}
          size={"lg"}
          className={cn(
            "mx-auto bg-blue text-white min-h-11! text-lg w-full py-2 rounded-xl uppercase justify-center",
            classes,
          )}
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
