import { ICart } from "@/types";
import { fetchInstance } from "../lib/utils";

export interface ICartResponseBody {
  success: string;
  message: string;
}
export interface ICartResponseBodyWithData extends ICartResponseBody {
  data: ICart;
}

export const getCartService = () =>
  fetchInstance<ICartResponseBodyWithData>("/cart", { method: "GET" });

export const addItemCartService = (body: {
  vendor_listing_id: string;
  quantity: number;
  listing_type?: string;
  admin_product_listing_id?: string;
  shipping_method_id?: string;
}) =>
  fetchInstance<ICartResponseBody>("/cart/items", {
    method: "POST",
    body: JSON.stringify(body),
  });

export const addItemsBulkCartService = (
  items: {
    vendor_listing_id: string;
    quantity: number;
    listing_type?: string;
    admin_product_listing_id?: string;
    shipping_method_id?: string;
  }[],
) =>
  fetchInstance<ICartResponseBody>("/cart/items/bulk", {
    method: "POST",
    body: JSON.stringify({ items }),
  });

export const updateItemQuantityCartService = (
  cart_item_id: string,
  quantity: number,
  shipping_method_id?: string,
) =>
  fetchInstance<ICartResponseBody>(`/cart/items/${cart_item_id}`, {
    method: "PUT",
    body: JSON.stringify({ quantity, shipping_method_id }),
  });

export const updateItemWarrantyCartService = (
  cart_item_id: string,
  warranty_plan_id: string | null,
) =>
  fetchInstance<ICartResponseBody>(`/cart/items/${cart_item_id}/warranty`, {
    method: "PATCH",
    body: JSON.stringify({ warranty_plan_id }),
  });

export const removeItemCartService = (cart_item_id: string) =>
  fetchInstance<ICartResponseBody>(`/cart/items/${cart_item_id}`, {
    method: "DELETE",
  });

export const clearCartService = () =>
  fetchInstance<ICartResponseBody>("/cart", { method: "DELETE" });

export const applyCouponCartService = (code: string) =>
  fetchInstance<ICartResponseBody>("/cart/coupon", {
    method: "POST",
    body: JSON.stringify({ code }),
  });

export const removeCouponCartService = () =>
  fetchInstance<ICartResponseBody>("/cart/coupon", { method: "DELETE" });

export const applyPromoCodeCartService = (code: string) =>
  fetchInstance<ICartResponseBody>("/cart/promo-code", {
    method: "POST",
    body: JSON.stringify({ code }),
  });

export const removePromoCodeCartService = () =>
  fetchInstance<ICartResponseBody>("/cart/promo-code", { method: "DELETE" });

export const mergeGuestCartService = (guest_cart_token: string) =>
  fetchInstance<ICartResponseBody>("/cart/merge", {
    method: "POST",
    body: JSON.stringify({ guest_cart_token }),
  });
