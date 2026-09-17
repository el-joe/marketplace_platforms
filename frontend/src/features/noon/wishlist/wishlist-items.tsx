"use client";
import { products } from "@/public/dummyData";
import { Button } from "@/src/components/ui/button";
import { CheckIcon, EllipsisIcon, Share2Icon } from "lucide-react";
import { useTranslations } from "next-intl";
import React, { useEffect, useState } from "react";
import ItemCard from "./item-card";
import EmptyState from "./empty-state";
import { useQueryState } from "nuqs";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { Skeleton } from "@/src/components/ui/skeleton";
import WishlistOptionsMenu from "./wihlist-options-menu";
import { IWishlist } from "@/types";

export default function WishlistItems() {
  const t = useTranslations("wishlist");
  const [copied, setCopied] = useState(false);
  const [selectedGroupId] = useQueryState("wishlistCode");
  const { getWishlistGroup, wishlistGroup, isLoadingGroup, isLoadingGroups } =
    useWishlistContext();
  const isLoading = isLoadingGroup || isLoadingGroups;
  const handleShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: wishlistGroup?.group?.name,
          text: `${wishlistGroup?.group?.name} - ${wishlistGroup?.group?.items_count}`,
          url: window.location.href,
        });
      } catch {
        // User cancelled share
      }
    } else {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };
  useEffect(() => {
    if (!!selectedGroupId && wishlistGroup?.group.id !== selectedGroupId) {
      getWishlistGroup(selectedGroupId as string);
    }
  }, [selectedGroupId]);
  return (
    <div className="flex-1">
      <div className="flex gap-3 py-4 ps-4 md:border-b items-center border-border">
        {isLoading ? (
          <>
            <Skeleton className="h-8 w-32" />
            <Skeleton className="h-8 w-26 ms-auto" />
            <Skeleton className="h-8 w-26" />
          </>
        ) : (
          <>
            <h3 className="text-lg font-semibold text-light">
              <span className="hidden md:inline">
                {wishlistGroup?.group?.name}
              </span>
              <span className="md:hidden">
                {products.length} {t("items")}
              </span>
            </h3>
            {wishlistGroup?.group?.is_default && (
              <p className="bg-blue-2 rounded-2xl px-2 py-0.5 text-white text-xs">
                {t("default")}
              </p>
            )}
            {selectedGroupId && (
              <>
                <Button
                  variant={"outline"}
                  className={"ms-auto rounded-2xl px-4 text-base text-gray"}
                  onClick={handleShare}
                >
                  {copied ? (
                    <CheckIcon className="w-4 h-4 text-emerald-600" />
                  ) : (
                    <Share2Icon className="w-4 h-4 text-gray-600" />
                  )}
                  <span className="hidden md:inline">
                    {copied ? "Link Copied!" : "Share"}
                  </span>
                </Button>
                <WishlistOptionsMenu
                  group={wishlistGroup as IWishlist}
                  trigger={
                    <Button
                      variant={"outline"}
                      className={"rounded-2xl px-4 text-base text-gray"}
                    >
                      <EllipsisIcon />
                      <span className="hidden md:inline">{t("more")}</span>
                    </Button>
                  }
                />
              </>
            )}
          </>
        )}
      </div>
      {/* items grid */}
      {isLoadingGroup ? (
        <div className="flex gap-3 flex-wrap items-stretch md:py-4 ps-4">
          {Array.from({ length: 4 }).map((e, i) => (
            <Skeleton key={i} className="h-130 w-37 md:w-40 lg:w-48 xl:w-72" />
          ))}
        </div>
      ) : !wishlistGroup?.items.length ? (
        <EmptyState />
      ) : (
        <div className="flex gap-3 flex-wrap items-stretch md:py-4 ps-4">
          {wishlistGroup?.items.map((p) => (
            <ItemCard item={p} key={p.id} />
          ))}
        </div>
      )}
    </div>
  );
}
