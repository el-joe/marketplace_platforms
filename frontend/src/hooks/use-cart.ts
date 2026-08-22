"use client";

import { useCallback } from "react";
import {
  useQuery,
  useMutation,
  useQueryClient,
  type UseQueryResult,
} from "@tanstack/react-query";
import {
  addItemCartService,
  addItemsBulkCartService,
  applyCouponCartService,
  clearCartService,
  getCartService,
  ICartResponseBodyWithData,
  mergeGuestCartService,
  removeCouponCartService,
  removeItemCartService,
  updateItemQuantityCartService,
} from "../services/cart";
import { ICart } from "@/types";
import resolveCookie from "../helpers/resolveCookie";
import { ApiRequestError } from "../lib/utils";
import { deleteCookie, setCookie } from "cookies-next";
import toast from "react-hot-toast";

export const CART_TOKEN_KEY = "cart_token";

// -----------------------------------------------

export const CART_QUERY_KEY = ["cart"] as const;

// ------------------------------------------
export function useCart() {
  const queryClient = useQueryClient();

  const cartQuery: UseQueryResult<ICart, ApiRequestError> = useQuery({
    queryKey: CART_QUERY_KEY,
    queryFn: async () => {
      const { data } = await getCartService();
      const token = data.cart.session_token;
      if (token)
        setCookie(CART_TOKEN_KEY, token, { maxAge: 60 * 60 * 24 * 400 });
      return data;
    },
    staleTime: 30_000,
  });

  const invalidate = useCallback(
    () => queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY }),
    [queryClient],
  );

  const setCartData = useCallback(
    (responseCartBody: ICartResponseBodyWithData) => {
      const {
        data: { cart },
      } = responseCartBody;
      queryClient.setQueryData(CART_QUERY_KEY, cart);
    },
    [queryClient],
  );

  const onError = (err: Error) => {
    toast.error(err.message);
  };

  const addItem = useMutation({
    mutationFn: ({
      vendorListingId,
      quantity = 1,
      listingType = "vendor",
      adminProductListingId,
      shippingMethodId,
    }: {
      vendorListingId: string;
      quantity: number;
      listingType?: string;
      adminProductListingId?: string;
      shippingMethodId?: string;
    }) =>
      addItemCartService({
        vendor_listing_id: vendorListingId,
        quantity,
        listing_type: listingType,
        shipping_method_id: shippingMethodId,
        admin_product_listing_id: adminProductListingId,
      }),
    onSuccess: invalidate,
    onError,
  });

  const addItemsBulk = useMutation({
    mutationFn: (items: { vendor_listing_id: string; quantity: number }[]) =>
      addItemsBulkCartService(items),
    onSuccess: invalidate,
    onError,
  });

  const updateItemQuantity = useMutation({
    mutationFn: ({
      cartItemId,
      quantity,
    }: {
      cartItemId: string;
      quantity: number;
    }) => updateItemQuantityCartService(cartItemId, quantity),
    onSuccess: invalidate,
    onError,
  });

  const removeItem = useMutation({
    mutationFn: (cartItemId: string) => removeItemCartService(cartItemId),
    onSuccess: invalidate,
    onError,
  });

  const clearCart = useMutation({
    mutationFn: () => clearCartService(),
    onSuccess: invalidate,
    onError,
  });

  const applyCoupon = useMutation({
    mutationFn: (code: string) => applyCouponCartService(code),
    onSuccess: invalidate,
    onError,
  });

  const removeCoupon = useMutation({
    mutationFn: () => removeCouponCartService(),
    onSuccess: invalidate,
    onError,
  });

  // merge guest cart into the account cart after login
  const mergeCart = useMutation({
    mutationFn: async () => {
      const guestToken = await resolveCookie(CART_TOKEN_KEY);
      if (!guestToken) {
        // nothing to merge — just fetch as the now-authenticated user
        return getCartService();
      }
      const merged = await mergeGuestCartService(guestToken);
      deleteCookie(CART_TOKEN_KEY);
      return merged;
    },
    onSuccess: invalidate,
  });

  // drop the guest cart token after logout so the next fetch starts fresh
  const resetGuestCart = useCallback(() => {
    deleteCookie(CART_TOKEN_KEY);
    invalidate();
    // queryClient.removeQueries({ queryKey: CART_QUERY_KEY });
  }, [invalidate]);

  //   returns
  return {
    cart: cartQuery.data,
    isLoading: cartQuery.isLoading,
    isFetching: cartQuery.isFetching,
    error: cartQuery.error,
    refetchCart: cartQuery.refetch,

    addItem: addItem.mutateAsync,
    addItemsBulk: addItemsBulk.mutateAsync,
    updateItemQuantity: updateItemQuantity.mutateAsync,
    removeItem: removeItem.mutateAsync,
    clearCart: clearCart.mutateAsync,
    applyCoupon: applyCoupon.mutateAsync,
    applyCouponErr: applyCoupon?.error,
    removeCoupon: removeCoupon.mutateAsync,
    mergeCart: mergeCart.mutateAsync,
    resetGuestCart,

    targetItemMutating:
      (addItem.isPending && addItem.variables.vendorListingId) ||
      (addItemsBulk.isPending &&
        addItemsBulk.variables.map((e) => e.vendor_listing_id)) ||
      (updateItemQuantity.isPending &&
        updateItemQuantity.variables.cartItemId) ||
      (removeItem.isPending && removeItem.variables),

    isMutating:
      addItem.isPending ||
      addItemsBulk.isPending ||
      updateItemQuantity.isPending ||
      removeItem.isPending ||
      clearCart.isPending ||
      applyCoupon.isPending ||
      removeCoupon.isPending ||
      mergeCart.isPending,
  };
}
