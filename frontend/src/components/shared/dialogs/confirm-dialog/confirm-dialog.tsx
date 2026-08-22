"use client";

import { useState } from "react";
import type { ReactElement, ReactNode } from "react";
import { Dialog, DialogTrigger } from "./index";
import ConfirmDialogTrigger from "./confirm-dialog-trigger";
import ConfirmDialogContent from "./confirm-dialog-content";

type ConfirmDialogVariant = "danger" | "info";

type ConfirmDialogProps = {
  variant: ConfirmDialogVariant;
  triggerText?: ReactNode;
  triggerClassName?: string;
  trigger?: ReactElement;
  title: string;
  description?: string;
  confirmText: string;
  cancelText: string;
  loadingText?: string;
  confirmError?: string | null;
  onConfirm: () => void | Promise<void>;
};

/**
 * Shared trigger-button + confirmation dialog for destructive ("danger") or
 * neutral ("info") actions. Owns its own open/loading state — on confirm it
 * awaits `onConfirm`, closing the dialog only if it resolves; a thrown/
 * rejected `onConfirm` keeps the dialog open so the user can see the error
 * and retry.
 */
export default function ConfirmDialog({
  variant,
  triggerText,
  triggerClassName,
  trigger,
  title,
  description,
  confirmText,
  cancelText,
  loadingText,
  confirmError,
  onConfirm,
}: ConfirmDialogProps) {
  const [open, setOpen] = useState(false);
  const [isConfirming, setIsConfirming] = useState(false);

  const handleConfirm = async () => {
    setIsConfirming(true);
    try {
      await onConfirm();
      setOpen(false);
    } catch {
      // Keep the dialog open on failure so the user can see the error and retry.
    } finally {
      setIsConfirming(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      {!!trigger ? (
        <DialogTrigger render={trigger} />
      ) : (
        <ConfirmDialogTrigger
          variant={variant}
          triggerText={triggerText}
          triggerClassName={triggerClassName}
        />
      )}
      <ConfirmDialogContent
        variant={variant}
        title={title}
        description={description}
        confirmText={confirmText}
        cancelText={cancelText}
        loadingText={loadingText}
        isConfirming={isConfirming}
        confirmError={confirmError}
        onCancel={() => setOpen(false)}
        onConfirm={handleConfirm}
      />
    </Dialog>
  );
}
