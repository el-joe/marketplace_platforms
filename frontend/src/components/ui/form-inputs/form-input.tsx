"use client";

import { useFormContext } from "react-hook-form";
import { Input } from "@/src/components/ui/base-inputs/input";
import { getErrorMessage } from "./utils";

type Props = React.ComponentProps<typeof Input> & {
  name: string;
  containerClass?: string;
};

export default function FormInput({ name, containerClass, ...props }: Props) {
  const {
    register,
    formState: { errors },
  } = useFormContext();
  const error = getErrorMessage(errors, name);

  return (
    <div className={containerClass}>
      <Input
        aria-invalid={!!error}
        {...props}
        {...register(name)}
        invalid={!!error}
      />
      {error && <p className="text-xs text-destructive mt-1.5">{error}</p>}
    </div>
  );
}
