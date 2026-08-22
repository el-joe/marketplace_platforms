"use client";

import { Controller, useFormContext } from "react-hook-form";
import { User } from "lucide-react";
import type { ProfileFormValues } from "../helpers/profile.schema";
import ToggleButton from "./toggle-button";

type Props = {
  label: string;
  maleLabel: string;
  femaleLabel: string;
};

export default function GenderField({ label, maleLabel, femaleLabel }: Props) {
  const { control } = useFormContext<ProfileFormValues>();

  return (
    <div>
      <label className="text-sm mb-1.5 block">{label}</label>
      <Controller
        control={control}
        name="gender"
        render={({ field }) => (
          <div className="flex items-center gap-3">
            <ToggleButton
              active={field.value === "male"}
              onClick={() => field.onChange("male")}
            >
              <User className="size-4" />
              {maleLabel}
            </ToggleButton>
            <ToggleButton
              active={field.value === "female"}
              onClick={() => field.onChange("female")}
            >
              <User className="size-4" />
              {femaleLabel}
            </ToggleButton>
          </div>
        )}
      />
    </div>
  );
}
