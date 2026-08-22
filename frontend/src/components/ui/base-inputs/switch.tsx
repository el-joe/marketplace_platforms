"use client";

import * as React from "react";
import { Switch as SwitchPrimitive } from "@base-ui/react/switch";

import { cn } from "@/src/lib/utils";
import { Field, FieldLabel } from "@/src/components/ui/field";

function Switch({
  className,
  label,
  id,
  ...props
}: SwitchPrimitive.Root.Props & { label?: React.ReactNode }) {
  const generatedId = React.useId();
  const switchId = id ?? generatedId;

  const switchControl = (
    <SwitchPrimitive.Root
      id={switchId}
      data-slot="switch"
      className={cn(
        "peer inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full border border-input bg-gray-2 p-0.5 transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-checked:border-blue-3 data-checked:bg-blue-3",
        className,
      )}
      {...props}
    >
      <SwitchPrimitive.Thumb
        data-slot="switch-thumb"
        className="size-5 rounded-full bg-white shadow-sm transition-transform data-checked:translate-x-4 data-unchecked:translate-x-0"
      />
    </SwitchPrimitive.Root>
  );

  if (!label) {
    return switchControl;
  }

  return (
    <Field orientation="horizontal">
      {switchControl}
      <FieldLabel htmlFor={switchId}>{label}</FieldLabel>
    </Field>
  );
}

export { Switch };
