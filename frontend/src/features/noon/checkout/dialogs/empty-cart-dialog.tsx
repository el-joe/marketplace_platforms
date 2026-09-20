import { Link } from "@/i18n/navigation";
import {
  Dialog,
  DialogContent,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { useTranslations } from "next-intl";
import React from "react";

type Props = {
  open: boolean;
};

export default function EmptyCartDialog({ open }: Props) {
  const t = useTranslations("checkout");
  return (
    <Dialog open={open}>
      <DialogContent
        className="max-w-[480px] p-6 rounded bg-white gap-4 text-black"
        showCloseButton={false}
      >
        <p className="text-red mb-4 text-lg">{t("yourCartIsEmpty")}</p>
        <p className="text-xl font-semibold capitalize">
          {t("whatWouldYouLikeToDo")}
        </p>
        <Link href={"/cart"} className="block! w-full!">
          <Button
            variant={"outline"}
            className={
              " shadow-md py-3 text-blue text-base rounded uppercase w-full"
            }
          >
            {t("ReturnToCart")}
          </Button>
        </Link>
      </DialogContent>
    </Dialog>
  );
}
