"use client";
import { useCartContext } from "@/src/providers/cart-provider";
import { useWarrantySelection } from "../warranty-selection-context";

/**
 * Warranty selection on the product page.
 *
 * Before the product is in the cart, picking a plan only stores it locally and
 * CartButton sends it with the add-to-cart request. Once the product is in the
 * cart, CartButton becomes a quantity counter and never adds again, so the plan
 * is written straight to the existing cart item instead. Without that, a plan
 * picked after the product was already in the cart never reached the cart.
 */
export function useExtendedWarrantyActions(listingId: string) {
  const { cart, updateItemWarranty, isMutating, targetItemMutating } =
    useCartContext();
  const { selectedPlanId, selectPlan: setSelectedPlan } =
    useWarrantySelection();

  const cartItem = cart?.cart?.items.find(
    (item) => item.listing_id === listingId,
  );

  // Once in the cart, the cart item is the source of truth for the plan.
  const activePlanId = cartItem
    ? (cartItem.warranty_plan_id ?? null)
    : selectedPlanId;

  const togglePlan = (planId: string) => {
    const nextPlanId = activePlanId === planId ? null : planId;

    if (!cartItem) {
      setSelectedPlan(nextPlanId);
      return;
    }

    updateItemWarranty({
      cartItemId: cartItem.cart_item_id,
      warrantyPlanId: nextPlanId,
    }).catch(() => {
      // onError in use-cart.ts already surfaces a toast.
    });
  };

  // updateItemWarranty reports the plan being set as targetItemMutating;
  // clearing a plan sends null, so fall back to the currently active plan.
  const isPlanPending = (planId: string) =>
    Boolean(cartItem) &&
    isMutating &&
    (targetItemMutating === planId ||
      (targetItemMutating === "" && activePlanId === planId));

  return {
    activePlanId,
    togglePlan,
    isPlanPending,
    isMutating,
    isInCart: Boolean(cartItem),
  };
}
