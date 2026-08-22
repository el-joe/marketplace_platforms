"use client";
import { ICart } from "@/types";
import { createContext, useContext } from "react";
import {
  QueryObserverResult,
  RefetchOptions,
  UseMutateAsyncFunction,
} from "@tanstack/react-query";
import { useCart } from "../hooks/use-cart";
import { ApiRequestError } from "../lib/utils";
import { ICartResponseBody } from "../services/cart";

interface ICartContext {
  cart: ICart | undefined;
  isLoading: boolean;
  isFetching: boolean;
  error: ApiRequestError | null;
  refetchCart: (
    options?: RefetchOptions,
  ) => Promise<QueryObserverResult<ICart, ApiRequestError>>;
  addItem: UseMutateAsyncFunction<
    ICartResponseBody,
    Error,
    {
      vendorListingId: string;
      quantity: number;
      listingType?: string;
      adminProductListingId?: string;
      shippingMethodId?: string;
    },
    unknown
  >;
  addItemsBulk: UseMutateAsyncFunction<
    ICartResponseBody,
    Error,
    {
      vendor_listing_id: string;
      quantity: number;
    }[],
    unknown
  >;
  updateItemQuantity: UseMutateAsyncFunction<
    ICartResponseBody,
    Error,
    {
      cartItemId: string;
      quantity: number;
    },
    unknown
  >;
  removeItem: UseMutateAsyncFunction<ICartResponseBody, Error, string, unknown>;
  clearCart: UseMutateAsyncFunction<ICartResponseBody, Error, void, unknown>;
  applyCoupon: UseMutateAsyncFunction<
    ICartResponseBody,
    Error,
    string,
    unknown
  >;
  applyCouponErr: Error | null;
  removeCoupon: UseMutateAsyncFunction<ICartResponseBody, Error, void, unknown>;
  mergeCart: UseMutateAsyncFunction<ICartResponseBody, Error, void, unknown>;
  resetGuestCart: () => void;
  targetItemMutating: string | false | string[];
  isMutating: boolean;
}

const initialState: ICartContext = {
  cart: undefined,
  isLoading: true,
  isFetching: true,
  error: null,
  refetchCart: async (): Promise<
    QueryObserverResult<ICart, ApiRequestError>
  > => {
    throw new Error("Not implemented");
  },
  addItem: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  addItemsBulk: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  updateItemQuantity: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  removeItem: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  clearCart: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  applyCoupon: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  applyCouponErr: null,
  removeCoupon: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  mergeCart: async (): Promise<ICartResponseBody> => {
    throw new Error("Not implemented");
  },
  resetGuestCart: () => {},
  targetItemMutating: false,
  isMutating: false,
};

const cartContext = createContext<ICartContext>(initialState);

export const CartProvider = ({ children }: { children: React.ReactNode }) => {
  const {
    cart,
    isLoading,
    isFetching,
    error,
    refetchCart,
    addItem,
    addItemsBulk,
    updateItemQuantity,
    removeItem,
    clearCart,
    applyCoupon,
    applyCouponErr,
    removeCoupon,
    mergeCart,
    resetGuestCart,
    targetItemMutating,
    isMutating,
  } = useCart();
  return (
    <cartContext.Provider
      value={{
        cart,
        isLoading,
        isFetching,
        error,
        refetchCart,
        addItem,
        addItemsBulk,
        updateItemQuantity,
        removeItem,
        clearCart,
        applyCoupon,
        applyCouponErr,
        removeCoupon,
        mergeCart,
        resetGuestCart,
        targetItemMutating,
        isMutating,
      }}
    >
      {children}
    </cartContext.Provider>
  );
};

export const useCartContext = () => useContext(cartContext);
