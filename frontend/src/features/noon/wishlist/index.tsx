import { Button } from "@/src/components/ui/button";
import WishlistItems from "@/src/features/noon/wishlist/wishlist-items";
import WishlistsNav from "@/src/features/noon/wishlist/wishlists-nav";
import { getTranslations } from "next-intl/server";
import React from "react";
import CreateGroupDialog from "./dialogs/create-group-dialog";

export default async function Wishlist() {
  const t = await getTranslations("wishlist");
  return (
    <div className="container">
      {/* header */}
      <div className="flex justify-between items-stretch py-5 border-b border-border">
        <h2 className="text-2xl font-bold text-light">{t("wishlist")}</h2>
        <CreateGroupDialog
          trigger={
            <Button
              className={
                "uppercase text-white bg-blue-2 px-4 font-bold min-h-11!"
              }
            >
              {t("createNewWishlist")}
            </Button>
          }
        />
      </div>
      <div className="flex items-stretch min-h-screen flex-wrap">
        <WishlistsNav />
        <WishlistItems />
      </div>
    </div>
  );
}
