import { IWishlist, IWishlistGroup } from "@/types";
import { fetchInstance } from "../lib/utils";

export interface IWishlistResponseBody {
  success: string;
  message: string;
}
export interface IWishlistGroupsResponseBody extends IWishlistResponseBody {
  data: IWishlistGroup[];
}
export interface IWishlistGroupResponseBody extends IWishlistResponseBody {
  data: IWishlist;
}

export const getWishlistGroupsService = () =>
  fetchInstance<IWishlistGroupsResponseBody>("/wishlist/groups", {
    method: "GET",
  });

export const getWishlistGroupService = (groupId: string) =>
  fetchInstance<IWishlistGroupResponseBody>(`/wishlist/groups/${groupId}`, {
    method: "GET",
  });

export const createWishlistGroupService = (body: {
  name: string;
  is_public: boolean;
}) =>
  fetchInstance<IWishlistGroupsResponseBody>("/wishlist/groups", {
    method: "POST",
    body: JSON.stringify(body),
  });

export const updateWishlistGroupService = (
  groupId: string,
  body: {
    name?: string;
    is_public?: boolean;
    sort_order?: number;
    is_default?: boolean;
  },
) =>
  fetchInstance<IWishlistGroupsResponseBody>(`/wishlist/groups/${groupId}`, {
    method: "PUT",
    body: JSON.stringify(body),
  });

export const removeWishlistGroupService = (groupId: string) =>
  fetchInstance<IWishlistGroupsResponseBody>(`/wishlist/groups/${groupId}`, {
    method: "DELETE",
  });

export const addWishlistItemService = (body: {
  listing_id: string;
  product_variant_id: string;
  group_id?: string;
}) =>
  fetchInstance<IWishlistGroupResponseBody>("/wishlist/items", {
    method: "POST",
    body: JSON.stringify(body),
  });
export const removeWishlistItemService = (itemId: string) =>
  fetchInstance<IWishlistGroupResponseBody>(`/wishlist/items/${itemId}`, {
    method: "DELETE",
  });

export const moveWishlistItemService = (body: {
  item_ids: string[];
  target_group_id: string;
}) =>
  fetchInstance<IWishlistGroupResponseBody>("/wishlist/items/move", {
    method: "POST",
    body: JSON.stringify(body),
  });

export interface IWishlistCheckResponse {
  success: string;
  message: string;
  data: {
    in_wishlist: boolean;
    groups: [
      {
        id: string;
        name: string;
        is_default: boolean;
      },
    ];
  };
}

export const checkWishlistItemService = (listingId: string) =>
  fetchInstance<IWishlistCheckResponse>(
    `/wishlist/check?listing_id=${listingId}`,
    { method: "GET" },
  );
