"use client";

import { useState, type JSXElementConstructor, type ReactElement } from "react";
import { useTranslations } from "next-intl";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button } from "@/src/components/ui/button";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { useDeleteAccountActions } from "../helpers/use-delete-account-actions";
import {
  deleteAccountReasons,
  type DeleteAccountReason,
} from "../helpers/constants";

type Props = {
  trigger: ReactElement<unknown, string | JSXElementConstructor<unknown>>;
};

export default function DeleteAccountDialog({ trigger }: Props) {
  const t = useTranslations("profile");
  const { deleteAccount } = useDeleteAccountActions();

  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState<DeleteAccountReason | null>(null);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const canSubmit = !!reason && termsAccepted && !isDeleting;

  const handleOpenChange = (next: boolean) => {
    setOpen(next);
    if (!next) {
      setReason(null);
      setTermsAccepted(false);
    }
  };

  const handleSubmit = async () => {
    setIsDeleting(true);
    try {
      await deleteAccount();
      handleOpenChange(false);
    } catch {
      // Keep the dialog open on failure so the user can see the error toast and retry.
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger render={trigger} />

      <DialogContent className="max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{t("deleteAccountDialogTitle")}</DialogTitle>
        </DialogHeader>

        <div className="flex flex-col gap-2">
          <p className="font-bold text-sm">{t("deleteAccountReasonLabel")}</p>
          <Select
            value={reason}
            onValueChange={(value) => setReason(value as DeleteAccountReason)}
            placeholder={t("deleteAccountReasonPlaceholder")}
            triggerClass="w-full h-11 justify-between"
            items={deleteAccountReasons.map((r) => ({
              label: t(r.labelKey),
              value: r.value,
            }))}
          />
        </div>

        <p className="rounded-lg bg-gray-2 p-3 text-xs leading-relaxed text-light">
          {t("deleteAccountWarning")}
        </p>

        <p className="text-xs font-semibold">{t("deleteAccountTimingNote")}</p>

        <Checkbox
          label={t("deleteAccountTermsLabel")}
          checked={termsAccepted}
          onCheckedChange={(value) => setTermsAccepted(value === true)}
        />

        <Button
          variant="destructive"
          disabled={!canSubmit}
          onClick={handleSubmit}
          className="w-full uppercase font-bold"
        >
          {isDeleting ? t("saving") : t("deleteAccount")}
        </Button>
      </DialogContent>
    </Dialog>
  );
}
