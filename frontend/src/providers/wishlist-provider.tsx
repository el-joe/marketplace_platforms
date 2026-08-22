"use client";
import { IWishlist, IWishlistGroup } from "@/types";
import { createContext, useContext } from "react";
import {
  QueryObserverResult,
  RefetchOptions,
  UseMutateAsyncFunction,
  UseMutateFunction,
} from "@tanstack/react-query";
import { ApiRequestError } from "../lib/utils";
import { useWishlist } from "../hooks/use-wishlist";
import {
  IWishlistGroupResponseBody,
  IWishlistGroupsResponseBody,
} from "../services/wishlist";

interface IWishlistContext {
  wishlistGroups: IWishlistGroup[] | undefined;
  isLoadingGroups: boolean;
  isFetchingGroups: boolean;
  errorGroups: ApiRequestError | null;
  refetchGroups: (
    options?: RefetchOptions,
  ) => Promise<QueryObserverResult<IWishlistGroup[], ApiRequestError>>;
  getWishlistGroup: UseMutateFunction<IWishlist, Error, string, unknown>;
  wishlistGroup: IWishlist | undefined;
  isLoadingGroup: boolean;
  errorGroup: Error | null;
  createGroup: UseMutateAsyncFunction<
    IWishlistGroupsResponseBody,
    Error,
    {
      name: string;
      isPublic: boolean;
    },
    unknown
  >;
  createGroupError: Error | null;
  updateGroup: UseMutateAsyncFunction<
    IWishlistGroupsResponseBody,
    Error,
    {
      groupId: string;
      body: {
        name?: string;
        is_public?: boolean;
        sort_order?: number;
        is_isDefault?: boolean;
      };
    },
    unknown
  >;
  updateGroupError: Error | null;
  removeGroup: UseMutateAsyncFunction<
    IWishlistGroupsResponseBody,
    Error,
    string,
    unknown
  >;
  removeGroupError: Error | null;
  addItem: UseMutateAsyncFunction<
    IWishlistGroupResponseBody,
    Error,
    {
      listingId: string;
      productVariantId: string;
      groupId?: string;
    },
    unknown
  >;
  removeItem: UseMutateAsyncFunction<
    IWishlistGroupResponseBody,
    Error,
    string,
    unknown
  >;
  moveItem: UseMutateAsyncFunction<
    IWishlistGroupResponseBody,
    Error,
    {
      itemIds: string[];
      targetGroupId: string;
    },
    unknown
  >;
  isMutating: boolean;
}

const initialState: IWishlistContext = {
  wishlistGroups: undefined,
  wishlistGroup: undefined,
  isLoadingGroups: true,
  isFetchingGroups: true,
  errorGroups: null,
  refetchGroups: async (): Promise<
    QueryObserverResult<IWishlistGroup[], ApiRequestError>
  > => {
    throw new Error("Not implemented");
  },
  getWishlistGroup: async (): Promise<IWishlist> => {
    throw new Error("Not implemented");
  },
  isLoadingGroup: false,
  errorGroup: null,
  createGroup: async (): Promise<IWishlistGroupsResponseBody> => {
    throw new Error("Not implemented");
  },
  createGroupError: null,
  updateGroup: async (): Promise<IWishlistGroupsResponseBody> => {
    throw new Error("Not implemented");
  },
  updateGroupError: null,
  removeGroup: async (): Promise<IWishlistGroupsResponseBody> => {
    throw new Error("Not implemented");
  },
  removeGroupError: null,
  addItem: async (): Promise<IWishlistGroupResponseBody> => {
    throw new Error("Not implemented");
  },
  removeItem: async (): Promise<IWishlistGroupResponseBody> => {
    throw new Error("Not implemented");
  },
  moveItem: async (): Promise<IWishlistGroupResponseBody> => {
    throw new Error("Not implemented");
  },

  isMutating: false,
};

const wishlistContext = createContext<IWishlistContext>(initialState);

export const WishlistProvider = ({
  children,
}: {
  children: React.ReactNode;
}) => {
  const {
    wishlistGroups,
    isLoadingGroups,
    isFetchingGroups,
    errorGroups,
    refetchGroups,

    getWishlistGroup,
    wishlistGroup,
    isLoadingGroup,
    errorGroup,

    createGroup,
    createGroupError,
    updateGroup,
    updateGroupError,
    removeGroup,
    removeGroupError,
    addItem,
    removeItem,
    moveItem,
    isMutating,
  } = useWishlist();
  return (
    <wishlistContext.Provider
      value={{
        wishlistGroups,
        isLoadingGroups,
        isFetchingGroups,
        errorGroups,
        refetchGroups,

        getWishlistGroup,
        wishlistGroup,
        isLoadingGroup,
        errorGroup,

        createGroup,
        createGroupError,
        updateGroup,
        updateGroupError,
        removeGroup,
        removeGroupError,
        addItem,
        removeItem,
        moveItem,

        isMutating,
      }}
    >
      {children}
    </wishlistContext.Provider>
  );
};

export const useWishlistContext = () => useContext(wishlistContext);
