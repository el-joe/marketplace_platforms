"use client";

import { Controller, useFormContext } from "react-hook-form";
import { cn } from "@/src/lib/utils";
import type { registerFormValues } from "./schema";

type Props = {
  legend: string;
  options: { value: "male" | "female" | ""; label: string }[];
};

/** Native radios in a fieldset: keyboard + screen-reader friendly. "" = not specified. */
export default function GenderRadioGroup({ legend, options }: Props) {
  const { control } = useFormContext<registerFormValues>();

  return (
    <Controller
      control={control}
      name="gender"
      render={({ field }) => (
        <fieldset className="flex flex-col gap-1.5">
          <legend className="text-sm mb-1">{legend}</legend>
          <div className="flex flex-wrap gap-2">
            {options.map((option) => {
              const checked = (field.value ?? "") === option.value;
              return (
                <label
                  key={option.value || "unspecified"}
                  className={cn(
                    "h-10 px-4 rounded-lg border flex items-center gap-2 text-sm cursor-pointer has-focus-visible:ring-2 has-focus-visible:ring-blue-3",
                    checked
                      ? "border-blue-3 text-blue-3 bg-blue-3/5"
                      : "border-input text-foreground",
                  )}
                >
                  <input
                    type="radio"
                    name={field.name}
                    value={option.value}
                    checked={checked}
                    onChange={() =>
                      field.onChange(option.value === "" ? undefined : option.value)
                    }
                    onBlur={field.onBlur}
                    className="accent-blue-3"
                  />
                  {option.label}
                </label>
              );
            })}
          </div>
        </fieldset>
      )}
    />
  );
}
