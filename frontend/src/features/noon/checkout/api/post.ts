import { fetchInstance } from "@/src/lib/utils";
import { IPlaceOrderResponse } from "../types";
import { IPlaceOrderPayload } from "../types/place-order.type";
import { IPrepareCheckout } from "../types/checkout.type";

export const placeOrderService = (orderPayload: IPlaceOrderPayload) =>
  fetchInstance<{ data: IPlaceOrderResponse }>("/checkout/place-order", {
    method: "POST",
    body: JSON.stringify(orderPayload),
  });

export const createPrepareCheckoutService = (orderPayload: {
  address_id: number;
  country_payment_gateway_id: string;
  receiver_id?: string | null;
  coupon_code?: string | null;
  warranty_selections?: { listing_id: string; warranty_plan_id: string }[];
}) =>
  fetchInstance<{ data: IPrepareCheckout }>("/checkout/prepare", {
    method: "POST",
    body: JSON.stringify(orderPayload),
  });
