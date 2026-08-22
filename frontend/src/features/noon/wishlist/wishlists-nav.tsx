"use client";
import { Skeleton } from "@/src/components/ui/skeleton";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { IWishlistGroup } from "@/types";
import { ShoppingBagIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useQueryState } from "nuqs";
import React, { useEffect } from "react";

export default function WishlistsNav() {
  const { wishlistGroups, isLoadingGroups } = useWishlistContext();
  return (
    <div className="md:py-4 md:pe-4 border-b md:border-e flex-1 md:flex-0 border-border md:min-w-54 lg:min-w-90 2xl:min-w-120 flex md:flex-col items-stretch gap-4">
      {isLoadingGroups &&
        Array.from({ length: 4 }).map((e, i) => (
          <Skeleton key={i} className="h-22" />
        ))}
      {wishlistGroups?.map((group) => (
        <NavItem group={group} key={group.id} />
      ))}
    </div>
  );
}

const NavItem = ({ group }: { group: IWishlistGroup }) => {
  const t = useTranslations("wishlist");
  const [selectedWishlistId, setSelectedWishlistId] =
    useQueryState("wishlistCode");
  useEffect(() => {
    if (!selectedWishlistId && group.is_default) {
      setSelectedWishlistId(group.id);
    }
  }, [group.id, group.is_default, selectedWishlistId, setSelectedWishlistId]);
  return (
    <div
      className={`${selectedWishlistId === group.id ? "bg-gray-2" : ""} p-1 md:p-4 md:border border-border cursor-pointer`}
      onClick={() => setSelectedWishlistId(group.id)}
    >
      <div className="flex gap-2 mb-2 items-center">
        <h3 className="font-semibold text-base">{group.name}</h3>
        {group.is_default && (
          <p className="bg-blue-2 rounded-2xl px-2 py-0.5 text-white text-xs">
            {t("default")}
          </p>
        )}
      </div>
      <div className="hidden md:flex gap-2 items-center">
        <p className="text-gray text-sm">
          {group.items_count} {t("items")}
        </p>
        <ShoppingBagIcon size={"16px"} />
      </div>
    </div>
  );
};
