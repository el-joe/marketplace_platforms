"use client";

import * as React from "react";
import { cn } from "@/src/lib/utils";

type Props = {
  length?: number;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  className?: string;
};

export function OtpInput({
  length = 6,
  value,
  onChange,
  disabled,
  className,
}: Props) {
  const inputsRef = React.useRef<(HTMLInputElement | null)[]>([]);

  const handleChange = (
    index: number,
    event: React.ChangeEvent<HTMLInputElement>,
  ) => {
    const digits = event.target.value.replace(/\D/g, "").split("");
    if (digits.length === 0) {
      onChange(value.slice(0, index) + value.slice(index + 1));
      return;
    }

    const nextValue = value.split("");
    let cursor = index;
    for (const digit of digits) {
      nextValue[cursor] = digit;
      cursor++;
      if (cursor >= length) break;
    }
    onChange(nextValue.join("").slice(0, length));
    inputsRef.current[Math.min(cursor, length - 1)]?.focus();
  };

  const handleKeyDown = (
    index: number,
    event: React.KeyboardEvent<HTMLInputElement>,
  ) => {
    if (event.key === "Backspace" && !value[index] && index > 0) {
      inputsRef.current[index - 1]?.focus();
    }
  };

  return (
    <div className={cn("flex items-center gap-2", className)}>
      {Array.from({ length }).map((_, index) => (
        <input
          key={index}
          ref={(el) => {
            inputsRef.current[index] = el;
          }}
          value={value[index] ?? ""}
          onChange={(event) => handleChange(index, event)}
          onKeyDown={(event) => handleKeyDown(index, event)}
          disabled={disabled}
          inputMode="numeric"
          maxLength={1}
          className="h-14 w-12 rounded-lg border border-input text-center text-lg outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
        />
      ))}
    </div>
  );
}
