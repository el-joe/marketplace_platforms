"use client";

import { useId } from "react";
import { Controller, useFormContext } from "react-hook-form";
import { User } from "lucide-react";
import type { ProfileFormValues } from "../helpers/profile.schema";
import ToggleButton from "./toggle-button";

type Props = {
  label: string;
  maleLabel: string;
  femaleLabel: string;
  unspecifiedLabel: string;
};

/** Optional gender: male | female | null ("not specified"). Exposed as an ARIA radio group. */
export default function GenderField({
  label,
  maleLabel,
  femaleLabel,
  unspecifiedLabel,
}: Props) {
  const { control } = useFormContext<ProfileFormValues>();
  const labelId = useId();

  const options = [
    { value: "male" as const, label: maleLabel, icon: true },
    { value: "female" as const, label: femaleLabel, icon: true },
    { value: null, label: unspecifiedLabel, icon: false },
  ];

  return (
    <div>
      <span id={labelId} className="text-sm mb-1.5 block">
        {label}
      </span>
      <Controller
        control={control}
        name="gender"
        render={({ field }) => (
          <div
            role="radiogroup"
            aria-labelledby={labelId}
            className="flex flex-wrap items-center gap-3"
          >
            {options.map((option) => (
              <ToggleButton
                key={option.value ?? "unspecified"}
                role="radio"
                active={(field.value ?? null) === option.value}
                onClick={() => field.onChange(option.value)}
              >
                {option.icon && <User className="size-4" aria-hidden="true" />}
                {option.label}
              </ToggleButton>
            ))}
          </div>
        )}
      />
    </div>
  );
}
