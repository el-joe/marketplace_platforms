"use client";

import { useCallback } from "react";
import {
  useQuery,
  useMutation,
  useQueryClient,
  type UseQueryResult,
} from "@tanstack/react-query";
import { IWishlistGroup } from "@/types";
import { ApiRequestError } from "../lib/utils";
import toast from "react-hot-toast";
import {
  addWishlistItemService,
  checkWishlistItemService,
  createWishlistGroupService,
  getWishlistGroupService,
  getWishlistGroupsService,
  moveWishlistItemService,
  removeWishlistGroupService,
  removeWishlistItemService,
  updateWishlistGroupService,
} from "../services/wishlist";
import { useAuthContext } from "../providers/auth-provider";

// -----------------------------------------------

export const WISHLIST_GROUPS_QUERY_KEY = ["wishlistGroups"] as const;
export const WISHLIST_GROUP_QUERY_KEY = ["wishlistGroup"] as const;

// ------------------------------------------
export function useWishlist() {
  const queryClient = useQueryClient();
  const { isLogged } = useAuthContext();

  const wishlistGroupsQuery: UseQueryResult<IWishlistGroup[], ApiRequestError> =
    useQuery({
      queryKey: WISHLIST_GROUPS_QUERY_KEY,
      queryFn: async () => {
        const { data } = await getWishlistGroupsService();
        return data;
      },
      enabled: isLogged,
    });

  const invalidateGroups = useCallback(
    () =>
      queryClient.invalidateQueries({ queryKey: WISHLIST_GROUPS_QUERY_KEY }),
    [queryClient],
  );

  const onError = (err: Error) => {
    toast.error(err.message);
  };

  const getWishlistGroup = useMutation({
    mutationKey: WISHLIST_GROUP_QUERY_KEY,
    mutationFn: async (groupId: string) => {
      const data = await getWishlistGroupService(groupId);
      return data.data;
    },
    onError,
  });

  const invalidateGroup = useCallback(
    (groupId?: string) => {
      const id = groupId || getWishlistGroup.data?.group.id;
      if (!!id) {
        getWishlistGroup.mutate(id);
      }
    },
    [getWishlistGroup],
  );

  const createGroup = useMutation({
    mutationFn: ({ name, isPublic }: { name: string; isPublic: boolean }) =>
      createWishlistGroupService({ name, is_public: isPublic }),
    onSuccess: invalidateGroups,
    onError,
  });
  const updateGroup = useMutation({
    mutationFn: ({
      groupId,
      body,
    }: {
      groupId: string;
      body: {
        name?: string;
        is_public?: boolean;
        sort_order?: number;
        is_isDefault?: boolean;
      };
    }) => updateWishlistGroupService(groupId, body),
    onSuccess: invalidateGroups,
  });
  const removeGroup = useMutation({
    mutationFn: (groupId: string) => removeWishlistGroupService(groupId),
    onSuccess: invalidateGroups,
  });

  const addItem = useMutation({
    mutationFn: ({
      listingId,
      productVariantId,
      groupId,
    }: {
      listingId: string;
      productVariantId: string;
      groupId?: string;
    }) =>
      addWishlistItemService({
        listing_id: listingId,
        product_variant_id: productVariantId,
        group_id: groupId,
      }),
    onSuccess: (_, { groupId }) => invalidateGroup(groupId),
    onError,
  });

  const removeItem = useMutation({
    mutationFn: (itemId: string) => removeWishlistItemService(itemId),
    onSuccess: () => invalidateGroup(),
  });

  const checkItem = useCallback(
    (listingId: string) => checkWishlistItemService(listingId),
    [],
  );

  const moveItem = useMutation({
    mutationFn: ({
      itemIds,
      targetGroupId,
    }: {
      itemIds: string[];
      targetGroupId: string;
    }) =>
      moveWishlistItemService({
        item_ids: itemIds,
        target_group_id: targetGroupId,
      }),
    onSuccess: () => invalidateGroup(),
  });

  //   returns
  return {
    wishlistGroups: wishlistGroupsQuery.data,
    isLoadingGroups: wishlistGroupsQuery.isLoading,
    isFetchingGroups: wishlistGroupsQuery.isFetching,
    errorGroups: wishlistGroupsQuery.error,
    refetchGroups: wishlistGroupsQuery.refetch,

    getWishlistGroup: getWishlistGroup.mutate,
    wishlistGroup: getWishlistGroup.data,
    isLoadingGroup: getWishlistGroup.isPending,
    errorGroup: getWishlistGroup.error,

    createGroup: createGroup.mutateAsync,
    createGroupError: createGroup.error,
    updateGroup: updateGroup.mutateAsync,
    updateGroupError: updateGroup.error,
    removeGroup: removeGroup.mutateAsync,
    removeGroupError: removeGroup.error,
    addItem: addItem.mutateAsync,
    removeItem: removeItem.mutateAsync,
    moveItem: moveItem.mutateAsync,
    checkItem,

    isMutating:
      createGroup.isPending ||
      updateGroup.isPending ||
      removeGroup.isPending ||
      addItem.isPending ||
      removeItem.isPending ||
      moveItem.isPending,
  };
}
