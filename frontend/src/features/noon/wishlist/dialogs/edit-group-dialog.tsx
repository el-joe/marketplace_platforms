"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Button } from "@/src/components/ui/button";
import { FieldError } from "@/src/components/ui/field";
import { Spinner } from "@/src/components/ui/spinner";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { IWishlist } from "@/types";
import { useTranslations } from "next-intl";
import React, { ReactElement, useState } from "react";

type Props = {
  trigger: ReactElement<unknown, string>;
  group: IWishlist;
};

export default function EditGroupDialog({ trigger, group }: Props) {
  const t = useTranslations("wishlist");
  const [newWishlistName, setNewWishlistName] = useState(
    group?.group.name || "",
  );
  const [open, setOpen] = useState(false);
  const { isMutating, updateGroup, updateGroupError } = useWishlistContext();
  return (
    <Dialog onOpenChange={(e) => setOpen(e)} open={open}>
      <DialogTrigger render={trigger} />
      <DialogContent
        className={"max-w-xl"}
        onKeyDown={(e) => e.stopPropagation()}
      >
        <DialogHeader>
          <DialogTitle>{t("editWishlist")}</DialogTitle>
        </DialogHeader>
        <Input
          placeholder={t("enterWishlistName")}
          className="mt-6 border-black rounded-none"
          value={newWishlistName}
          onChange={(e) => {
            setNewWishlistName(e.target.value);
          }}
        />
        {updateGroupError && (
          <FieldError>{updateGroupError.message}</FieldError>
        )}

        <Button
          className={"bg-blue-2 py-3 mt-8 text-white"}
          onClick={() => {
            updateGroup({
              groupId: group?.group.id,
              body: { name: newWishlistName },
            }).then(() => {
              setOpen(false);
            });
          }}
          disabled={isMutating}
        >
          {isMutating ? <Spinner /> : t("save")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
