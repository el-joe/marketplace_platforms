import * as React from "react";
import { Input as InputPrimitive } from "@base-ui/react/input";

import { cn } from "@/src/lib/utils";
import { Field, FieldLabel } from "@/src/components/ui/field";

function Input({
  className,
  type,
  startIcon,
  endIcon,
  label,
  id,
  invalid,
  ...props
}: React.ComponentProps<"input"> & {
  startIcon?: React.ReactNode;
  endIcon?: React.ReactNode;
  label?: React.ReactNode;
  invalid?: boolean;
}) {
  const generatedId = React.useId();
  const inputId = id ?? generatedId;

  const input = (
    <div className="relative">
      {startIcon && (
        <div className="absolute left-2 top-1/2 -translate-y-1/2">
          {startIcon}
        </div>
      )}
      <InputPrimitive
        id={inputId}
        type={type}
        data-slot="input"
        className={cn(
          "w-full rounded-md border border-input bg-transparent px-3 py-2 text-base transition-colors outline-none file:inline-flex file:h-6 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground placeholder:text-sm",
          className,
          startIcon && "ps-10",
          endIcon && "pe-10",
          invalid && "border-red",
        )}
        {...props}
      />
      {endIcon && (
        <div className="absolute inset-e-2 top-1/2 -translate-y-1/2">
          {endIcon}
        </div>
      )}
    </div>
  );

  if (!label) {
    return input;
  }

  return (
    <Field>
      <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
      {input}
    </Field>
  );
}

export { Input };
