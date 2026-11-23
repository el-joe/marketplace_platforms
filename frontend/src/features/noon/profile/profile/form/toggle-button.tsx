"use client";

import { cn } from "@/src/lib/utils";

export default function ToggleButton({
  active,
  onClick,
  children,
  role,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
  /** "radio" inside a radiogroup; otherwise a pressed/unpressed toggle. */
  role?: "radio";
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      role={role}
      aria-checked={role === "radio" ? active : undefined}
      aria-pressed={role === "radio" ? undefined : active}
      className={cn(
        "h-12 px-5 rounded-lg border flex items-center gap-2 font-medium cursor-pointer",
        active
          ? "border-blue-3 text-blue-3 bg-blue-3/5"
          : "border-input text-foreground",
      )}
    >
      {children}
    </button>
  );
}
