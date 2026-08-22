"use client";

import { Controller, useFormContext } from "react-hook-form";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { getErrorMessage } from "./utils";

type Props = Omit<React.ComponentProps<typeof Checkbox>, "checked"> & {
  name: string;
};

export default function FormCheckbox({ name, ...props }: Props) {
  const {
    control,
    formState: { errors },
  } = useFormContext();
  const error = getErrorMessage(errors, name);

  return (
    <div>
      <Controller
        control={control}
        name={name}
        render={({ field }) => (
          <Checkbox
            aria-invalid={!!error}
            checked={!!field.value}
            onCheckedChange={field.onChange}
            {...props}
          />
        )}
      />
      {error && <p className="text-xs text-destructive mt-1.5">{error}</p>}
    </div>
  );
}
