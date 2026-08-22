"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Button } from "@/src/components/ui/button";
import { FieldError } from "@/src/components/ui/field";
import { Spinner } from "@/src/components/ui/spinner";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { useTranslations } from "next-intl";
import React, { ReactElement, useState } from "react";

type Props = {
  trigger: ReactElement<unknown, string>;
};

export default function CreateGroupDialog({ trigger }: Props) {
  const t = useTranslations("wishlist");
  const [newWishlistData, setNewWishlistData] = useState({
    name: "",
    isPublic: false,
    isDefault: false,
  });
  const [open, setOpen] = useState(false);
  const { createGroup, isMutating, createGroupError } = useWishlistContext();
  return (
    <Dialog onOpenChange={(e) => setOpen(e)} open={open}>
      <DialogTrigger render={trigger} />
      <DialogContent className={"max-w-xl"}>
        <DialogHeader>
          <DialogTitle>{t("createNewWishlist")}</DialogTitle>
        </DialogHeader>
        <Input
          placeholder={t("enterWishlistName")}
          className="mt-6 border-black rounded-none"
          value={newWishlistData.name}
          onChange={(e) =>
            setNewWishlistData((p) => ({ ...p, name: e.target.value }))
          }
        />
        {createGroupError && (
          <FieldError>{createGroupError.message}</FieldError>
        )}
        <Checkbox
          label={t("useThisAsDefaultWishlist")}
          checked={newWishlistData.isDefault}
          onCheckedChange={(e) =>
            setNewWishlistData((p) => ({ ...p, isDefault: e }))
          }
        />
        <Checkbox
          label={t("makeThisWishlistPublic")}
          checked={newWishlistData.isPublic}
          onCheckedChange={(e) =>
            setNewWishlistData((p) => ({ ...p, isPublic: e }))
          }
        />
        <Button
          className={"bg-blue-2 py-3 mt-8 text-white"}
          onClick={() => {
            createGroup(newWishlistData).then(() => {
              setOpen(false);
            });
          }}
          disabled={isMutating}
        >
          {isMutating ? <Spinner /> : t("create")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
