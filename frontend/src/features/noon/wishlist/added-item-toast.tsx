"use client";
import { Button } from "@/src/components/ui/button";
import { IWishlist, IWishlistGroup } from "@/types";
import { HeartIcon } from "lucide-react";
// import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import MoveItemDialog from "./dialogs/move-item-dialog";
import { IAddWishlistItemResponseBody } from "@/src/services/wishlist";

export const AddedItemToast = ({
  wishlist,
}: {
  wishlist: IAddWishlistItemResponseBody["data"];
}) => {
  //   const t = useTranslations("wishlist");
  toast.custom((tos) => {
    return (
      <div
        className={`max-w-md w-full bg-white shadow-lg rounded-lg transition-all duration-300
        ${tos.visible ? "animate-enter" : "animate-leave"}`}
      >
        <div className="flex gap-3 items-center w-full p-4">
          <HeartIcon className="size-6 fill-gray text-gray" />
          <p className="text-gray font-medium">
            added to &quot;{wishlist.group.name}&quot; wishlist{" "}
          </p>
          {/* <p>{t("addedTo$wishlist", { name: wishlist?.group?.name })}</p> */}
          <MoveItemDialog
            trigger={
              <Button className={"ms-auto bg-gray text-white px-4 rounded-2xl"}>
                Edit
              </Button>
            }
            itemId={wishlist?.item?.id}
            mode="cut"
          />
        </div>
      </div>
    );
  });
};
