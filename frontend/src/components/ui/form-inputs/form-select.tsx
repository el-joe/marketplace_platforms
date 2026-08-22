"use client";

import { Controller, useFormContext } from "react-hook-form";
import { Select } from "@/src/components/ui/base-inputs/select";
import { getErrorMessage } from "./utils";

type Props = Omit<React.ComponentProps<typeof Select>, "value"> & {
  name: string;
};

export default function FormSelect({ name, ...props }: Props) {
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
          <Select
            value={field.value}
            onValueChange={field.onChange}
            {...props}
          />
        )}
      />
      {error && <p className="text-xs text-destructive mt-1.5">{error}</p>}
    </div>
  );
}
