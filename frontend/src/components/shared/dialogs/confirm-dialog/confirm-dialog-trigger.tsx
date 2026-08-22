"use client";

import type { ReactNode } from "react";
import { cn } from "@/src/lib/utils";
import { Button } from "@/src/components/ui/button";
import { DialogTrigger } from "./index";

type Props = {
  variant: "danger" | "info";
  triggerText: ReactNode;
  triggerClassName?: string;
};

export default function ConfirmDialogTrigger({
  variant,
  triggerText,
  triggerClassName,
}: Props) {
  const isDanger = variant === "danger";

  return (
    <DialogTrigger
      render={
        <Button
          variant={isDanger ? "destructive" : undefined}
          className={cn(
            "font-semibold uppercase",
            !isDanger && "bg-blue-3 hover:bg-blue text-white",
            triggerClassName,
          )}
        />
      }
    >
      {triggerText}
    </DialogTrigger>
  );
}
