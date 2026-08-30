"use client";

import { BanIcon, Minus, Plus, Trash2 } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { cn } from "@/src/lib/utils";
import { Spinner } from "../ui/spinner";

interface CounterProps {
  value: number;
  onChange: (value: number) => void;
  onDelete?: () => void;
  min?: number;
  max?: number;
  step?: number;
  className?: string;
  disabled?: boolean;
  loading?: boolean;
}

export function Counter({
  value = 1,
  onChange,
  onDelete,
  min = 1,
  max = Infinity,
  step = 1,
  className,
  disabled = false,
  loading,
}: CounterProps) {
  const showDelete = value <= min && !!onDelete;

  const decrement = () => {
    if (disabled) return;
    onChange(Math.max(min, value - step));
  };

  const increment = () => {
    if (disabled) return;
    onChange(Math.min(max, value + step));
  };

  return (
    <div
      className={cn(
        "inline-flex items-center rounded-lg border border-border bg-white",
        className,
      )}
    >
      {showDelete ? (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
            onDelete();
          }}
          disabled={disabled}
          className=" rounded-none rounded-l-lg"
        >
          <Trash2 className="size-5" />
        </Button>
      ) : (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={(e) => {
            e.stopPropagation();
            e.preventDefault();
            decrement();
          }}
          disabled={disabled || value <= min}
          className=" rounded-none rounded-l-lg"
        >
          <Minus className="size-5" />
        </Button>
      )}

      <span className="flex min-w-6 items-center justify-center text-sm font-medium flex-1">
        {loading ? <Spinner /> : value}
      </span>

      <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={(e) => {
          e.stopPropagation();
          e.preventDefault();
          increment();
        }}
        disabled={disabled || value >= max}
        className=" rounded-none rounded-r-lg"
      >
        {value >= max ? (
          <BanIcon className="size-5" />
        ) : (
          <Plus className="size-5" />
        )}
      </Button>
    </div>
  );
}
