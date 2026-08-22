"use client";

import { cn } from "@/src/lib/utils";
import { Button } from "@/src/components/ui/button";
import {
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "./index";
import { FieldError } from "@/src/components/ui/field";

type Props = {
  variant: "danger" | "info";
  title: string;
  description?: string;
  confirmText: string;
  cancelText: string;
  loadingText?: string;
  isConfirming: boolean;
  confirmError?: string | null;
  onCancel: () => void;
  onConfirm: () => void;
};

export default function ConfirmDialogContent({
  variant,
  title,
  description,
  confirmText,
  cancelText,
  loadingText,
  isConfirming,
  confirmError,
  onCancel,
  onConfirm,
}: Props) {
  const isDanger = variant === "danger";

  return (
    <DialogContent className="max-w-[400px]">
      <DialogHeader>
        <DialogTitle className={cn(isDanger && "text-red")}>
          {title}
        </DialogTitle>
        {description && <DialogDescription>{description}</DialogDescription>}
        {confirmError && <FieldError>{confirmError}</FieldError>}
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" onClick={onCancel} disabled={isConfirming}>
          {cancelText}
        </Button>
        <Button
          variant={isDanger ? "destructive" : undefined}
          className={cn(!isDanger && "bg-blue-3 hover:bg-blue text-white")}
          onClick={onConfirm}
          disabled={isConfirming}
        >
          {isConfirming ? (loadingText ?? confirmText) : confirmText}
        </Button>
      </DialogFooter>
    </DialogContent>
  );
}
