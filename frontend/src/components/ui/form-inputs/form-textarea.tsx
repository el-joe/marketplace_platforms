"use client";

import { useFormContext } from "react-hook-form";
import { Textarea } from "@/src/components/ui/base-inputs/textarea";
import { getErrorMessage } from "./utils";

type Props = React.ComponentProps<typeof Textarea> & {
  name: string;
};

export default function FormTextarea({ name, ...props }: Props) {
  const {
    register,
    formState: { errors },
  } = useFormContext();
  const error = getErrorMessage(errors, name);

  return (
    <div>
      <Textarea aria-invalid={!!error} {...props} {...register(name)} />
      {error && <p className="text-xs text-destructive mt-1.5">{error}</p>}
    </div>
  );
}
