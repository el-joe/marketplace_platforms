"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { Spinner } from "@/src/components/ui/spinner";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { Item } from "@/types/wishlist.type";
import { useTranslations } from "next-intl";
import React, { ReactElement, useState } from "react";
import CreateGroupDialog from "./create-group-dialog";
import { EarthIcon, LockKeyholeIcon, PlusIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";
import { useQueryState } from "nuqs";

type Props = {
  trigger: ReactElement<unknown, string>;
  item?: Item;
  itemId?: string;
  mode: "copy" | "cut";
};

export default function MoveItemDialog({ trigger, item, mode, itemId }: Props) {
  const t = useTranslations("wishlist");
  const [open, setOpen] = useState(false);
  const [targetGroup, setTargetGroup] = useState<string | null>(null);
  const [currentGroup] = useQueryState("wishlistCode");
  const { moveItem, isMutating, wishlistGroups, addItem } =
    useWishlistContext();
  return (
    <Dialog
      onOpenChange={(e) => {
        setOpen(e);
        setTargetGroup(null);
      }}
      open={open}
    >
      <DialogTrigger render={trigger} />
      <DialogContent
        className={"max-w-lg"}
        onKeyDown={(e) => e.stopPropagation()}
      >
        <DialogHeader className="mb-8">
          <DialogTitle>
            {mode === "copy" ? t("addToWishlist") : t("moveToWishlist")}
          </DialogTitle>
        </DialogHeader>

        {wishlistGroups
          ?.filter((g) => g.id !== currentGroup)
          .map((g) => (
            <div
              key={g.id}
              className={`${targetGroup === g.id ? "bg-gray-2" : ""} p-1 md:p-4 md:border border-border cursor-pointer flex justify-between items-center`}
              onClick={() => setTargetGroup(g.id)}
            >
              <div>
                <div className="flex gap-2 mb-2 items-center">
                  <h3 className="font-semibold text-base">{g.name}</h3>
                  {g.is_default && (
                    <p className="bg-blue-2 rounded-2xl px-2 py-0.5 text-white text-xs">
                      {t("default")}
                    </p>
                  )}
                </div>
                <div className="hidden md:flex gap-2 items-center">
                  <p className="text-gray text-sm">
                    {g.items_count} {t("items")}
                  </p>
                  {g.is_public ? (
                    <EarthIcon size={"16px"} />
                  ) : (
                    <LockKeyholeIcon size={"16px"} />
                  )}
                </div>
              </div>
              <div
                className={cn(
                  "w-4 h-4 rounded-full outline outline-offset-3 outline-gray ",
                  targetGroup === g.id && "bg-blue-400 ",
                )}
              />
            </div>
          ))}

        <CreateGroupDialog
          trigger={
            <Button
              className={"bg-gray py-3 mt-8 text-white"}
              disabled={isMutating}
            >
              <PlusIcon className="size-6" />
              {t("createNewWishlist")}
            </Button>
          }
        />
        <Button
          className={"bg-blue-2 py-3 text-white"}
          onClick={() => {
            switch (mode) {
              case "copy":
                addItem({
                  listingId: item?.listing?.listing_id || "",
                  productVariantId: item?.listing?.variant_id || "",
                  groupId: targetGroup as string,
                });
                break;
              case "cut":
                moveItem({
                  itemIds: [item?.id || itemId || ""],
                  targetGroupId: targetGroup as string,
                }).then(() => {
                  setOpen(false);
                });
                break;
            }
          }}
          disabled={isMutating || !targetGroup}
        >
          {isMutating ? <Spinner /> : t("done")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
