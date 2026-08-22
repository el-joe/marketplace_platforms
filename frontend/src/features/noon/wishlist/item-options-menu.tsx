"use client";
import ConfirmDialog from "@/src/components/shared/dialogs/confirm-dialog/confirm-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { CopyIcon, MoveIcon, TrashIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React, { useState } from "react";
import { Item } from "@/types/wishlist.type";
import MoveItemDialog from "./dialogs/move-item-dialog";

type Props = {
  trigger: React.ReactElement<unknown, string>;
  item: Item;
};

export default function WishlistItemOptionsMenu({ trigger, item }: Props) {
  const t = useTranslations("wishlist");
  const { removeGroupError, removeItem } = useWishlistContext();
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  return (
    <DropdownMenu open={isDropdownOpen} onOpenChange={setIsDropdownOpen}>
      <DropdownMenuTrigger render={trigger} />
      <DropdownMenuContent className={"w-auto min-w-3xs"}>
        <MoveItemDialog
          item={item}
          mode="cut"
          trigger={
            <DropdownMenuItem className={"py-3"} closeOnClick={false}>
              <MoveIcon className="size-5" />
              {t("moveToAnotherWishlist")}
            </DropdownMenuItem>
          }
        />
        <DropdownMenuSeparator />
        <MoveItemDialog
          item={item}
          mode="copy"
          trigger={
            <DropdownMenuItem className={"py-3"} closeOnClick={false}>
              <CopyIcon className="size-5" />
              {t("addToAnotherWishlist")}
            </DropdownMenuItem>
          }
        />
        <DropdownMenuSeparator />
        <ConfirmDialog
          trigger={
            <DropdownMenuItem
              className={"py-3"}
              closeOnClick={false}
              variant="destructive"
            >
              <TrashIcon className="size-5" />
              {t("delete")}
            </DropdownMenuItem>
          }
          variant="danger"
          cancelText={t("cancel")}
          loadingText={t("deleting")}
          confirmText={t("delete")}
          onConfirm={async () => {
            await removeItem(item.id);
            setIsDropdownOpen(false);
          }}
          confirmError={removeGroupError?.message}
          description={t("areYouSureYouWantToDeleteThisItem")}
          title={t("deleteItem")}
        />
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
