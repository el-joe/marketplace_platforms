"use client";

import { CalendarIcon } from "lucide-react";
import { format } from "date-fns";
import type { DateRange } from "react-day-picker";
import { Button } from "@/src/components/ui/button";
import { Calendar } from "@/src/components/ui/calendar";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/src/components/ui/popover";
import { cn } from "@/src/lib/utils";

type Props = {
  value?: DateRange;
  onChange: (range: DateRange | undefined) => void;
  placeholder?: string;
  numberOfMonths?: number;
  className?: string;
};

export default function DateRangePicker({
  value,
  onChange,
  placeholder,
  numberOfMonths = 2,
  className,
}: Props) {
  const label = value?.from
    ? value.to
      ? `${format(value.from, "dd MMM yyyy")} - ${format(value.to, "dd MMM yyyy")}`
      : format(value.from, "dd MMM yyyy")
    : placeholder;

  return (
    <Popover>
      <PopoverTrigger
        render={
          <Button
            type="button"
            variant="outline"
            className={cn(
              "h-9 justify-start gap-2 border-border bg-gray-2 font-normal text-primary hover:bg-gray-2",
              !value?.from && "text-light",
              className,
            )}
          >
            <CalendarIcon className="size-4 shrink-0" />
            {label}
          </Button>
        }
      />
      <PopoverContent className="w-auto p-2" align="start">
        <Calendar
          mode="range"
          selected={value}
          onSelect={onChange}
          defaultMonth={value?.from}
          numberOfMonths={numberOfMonths}
        />
      </PopoverContent>
    </Popover>
  );
}
