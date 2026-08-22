"use client";
import ConfirmDialog from "@/src/components/shared/dialogs/confirm-dialog/confirm-dialog";
import { Switch } from "@/src/components/ui/base-inputs/switch";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
import { FieldLabel } from "@/src/components/ui/field";
import { useWishlistContext } from "@/src/providers/wishlist-provider";
import { IWishlist } from "@/types";
import { CheckCircle2Icon, PencilIcon, TrashIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import React, { useState } from "react";
import EditGroupDialog from "./dialogs/edit-group-dialog";
import { useQueryState } from "nuqs";
import { Spinner } from "@/src/components/ui/spinner";

type Props = {
  trigger: React.ReactElement<unknown, string>;
  group: IWishlist;
};

export default function WishlistOptionsMenu({ trigger, group }: Props) {
  const t = useTranslations("wishlist");
  const [selectedWishlist, setSelectedWishlist] = useQueryState("wishlistCode");
  const {
    removeGroup,
    removeGroupError,
    updateGroup,
    isMutating,
    isLoadingGroup,
    wishlistGroups,
  } = useWishlistContext();
  const [isDropdownOpen, setIsDropdownOpen] = useState(false);
  return (
    <DropdownMenu open={isDropdownOpen} onOpenChange={setIsDropdownOpen}>
      <DropdownMenuTrigger render={trigger} />
      <DropdownMenuContent className={"w-auto min-w-3xs"}>
        <EditGroupDialog
          group={group}
          trigger={
            <DropdownMenuItem className={"py-3"} closeOnClick={false}>
              <PencilIcon className="size-5" />
              {t("edit")}
            </DropdownMenuItem>
          }
        />
        <DropdownMenuSeparator />
        {!group?.group?.is_default && (
          <>
            <DropdownMenuItem
              className={"py-3"}
              onClick={() =>
                updateGroup({
                  groupId: group?.group.id,
                  body: { is_isDefault: true },
                })
              }
            >
              <CheckCircle2Icon className="size-5" />
              {t("makeThisDefaultWishlist")}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
          </>
        )}
        <DropdownMenuItem className={"py-0"} closeOnClick={false}>
          <FieldLabel className="py-3 cursor-pointer bg-transparent!">
            <span>{t("enable/disablePublicSharing")}</span>
            {isMutating || isLoadingGroup ? (
              <Spinner />
            ) : (
              <Switch
                disabled={isMutating}
                checked={
                  wishlistGroups?.find((g) => g.id === group?.group.id)
                    ?.is_public
                }
                onCheckedChange={(e) =>
                  updateGroup({
                    groupId: group?.group.id,
                    body: { is_public: e },
                  })
                }
              />
            )}
          </FieldLabel>
        </DropdownMenuItem>
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
            await removeGroup(group?.group.id);
            setSelectedWishlist(null);
            setIsDropdownOpen(false);
          }}
          confirmError={removeGroupError?.message}
          description={t("areYouSureYouWantToDeleteThisWishlist")}
          title={t("deleteWishlist")}
        />
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
