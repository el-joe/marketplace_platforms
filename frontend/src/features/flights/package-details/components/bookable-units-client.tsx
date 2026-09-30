"use client";

import { useTranslations, useLocale } from "next-intl";
import { AlertCircleIcon, CheckCircle2Icon, RefreshCwIcon, BedDoubleIcon, ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { DayPicker, type DayButtonProps } from "react-day-picker";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { Skeleton } from "@/src/components/ui/skeleton";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";
import { Select } from "@/src/components/ui/base-inputs/select";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import type { BookableUnitSummary, BookableUnitTimeSlot } from "../../helpers/types";
import { useUnitBooking, toDateStr } from "../helpers/use-unit-booking";

type Props = {
  units: BookableUnitSummary[];
  currency: CurrencyCode;
};

export default function BookableUnitsClient({ units, currency }: Props) {
  const t = useTranslations("flights.packageDetails");
  const locale = useLocale();
  const booking = useUnitBooking(units);

  const selectedUnit = units.find((u) => u.id === booking.unitId);

  const unitItems = units.map((u) => ({
    value: u.id,
    label: `${u.name} — ${t("unitCapacity", { count: u.capacity })}`,
  }));

  const slotItems = [
    { value: "", label: t("fullDay") },
    ...(booking.calendar?.time_slots ?? []).map((s: BookableUnitTimeSlot) => ({
      value: s.id,
      label: `${t(`slot_${s.slot_type}`)} ${s.starts_at.slice(0, 5)}–${s.ends_at.slice(0, 5)}`,
    })),
  ];

  return (
    <Card className="border border-border shadow-sm p-6 flex flex-col gap-6">
      {/* Unit selector */}
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-primary">{t("selectUnit")}</p>
        <Select
          items={unitItems}
          value={booking.unitId}
          onValueChange={(v) => { if (v) booking.onUnitChange(v); }}
          triggerClass="w-full"
        />
      </div>

      {/* Unit photo */}
      {selectedUnit?.primary_photo_url ? (
        <img
          src={selectedUnit.primary_photo_url}
          alt={selectedUnit.name}
          className="w-full h-48 object-cover rounded-xl"
        />
      ) : (
        <div className="w-full h-32 rounded-xl bg-muted flex items-center justify-center gap-2 text-muted-foreground text-sm">
          <BedDoubleIcon className="size-5 opacity-50" />
          <span>{t("noPhoto")}</span>
        </div>
      )}

      {/* Calendar */}
      <div>
        <p className="text-sm font-medium text-primary mb-3">{t("selectDates")}</p>

        {/* Month navigator — always visible */}
        <MonthNavigator
          month={booking.month}
          locale={locale}
          onPrev={() => {
            const d = new Date(booking.month);
            d.setMonth(d.getMonth() - 1);
            booking.onMonthChange(d);
          }}
          onNext={() => {
            const d = new Date(booking.month);
            d.setMonth(d.getMonth() + 1);
            booking.onMonthChange(d);
          }}
        />

        {booking.calendarLoading && (
          <div className="grid grid-cols-7 gap-1">
            {Array.from({ length: 35 }).map((_, i) => (
              <Skeleton key={i} className="rounded-lg aspect-square" />
            ))}
          </div>
        )}

        {!booking.calendarLoading && booking.calendar && (
          <DayPicker
            mode="range"
            month={booking.month}
            onMonthChange={booking.onMonthChange}
            selected={booking.selectedRange}
            onSelect={booking.onRangeSelect}
            disabled={booking.isDisabled}
            showOutsideDays={false}
            components={{
              DayButton: (props: DayButtonProps) => {
                const dateStr = toDateStr(props.day.date);
                const dayData = booking.byDate.get(dateStr);
                const price = dayData?.price_day_only ?? dayData?.price_with_overnight;
                return (
                  <button
                    {...props}
                    className={[
                      props.className,
                      "flex flex-col items-center justify-center gap-0.5 py-1 w-full",
                    ]
                      .filter(Boolean)
                      .join(" ")}
                  >
                    <span className="leading-none">{props.day.date.getDate()}</span>
                    {price != null && dayData?.is_available && (
                      <span className="text-[9px] opacity-60 leading-none tabular-nums">
                        {price}
                      </span>
                    )}
                  </button>
                );
              },
            }}
            classNames={{
              root: "w-full",
              months: "w-full",
              month: "w-full",
              month_caption: "hidden",
              nav: "hidden",
              month_grid: "w-full border-collapse",
              weekdays: "flex",
              weekday: "flex-1 text-center text-xs text-muted-foreground pb-2 select-none",
              week: "flex",
              day: "flex-1 aspect-square p-0.5",
              day_button:
                "w-full h-full rounded-lg text-xs hover:bg-muted transition-colors",
              selected: "",
              range_start: "[&>button]:!bg-blue-3 [&>button]:!text-white",
              range_end: "[&>button]:!bg-blue-3 [&>button]:!text-white",
              range_middle: "[&>button]:!bg-blue-3/20 [&>button]:rounded-none",
              disabled:
                "[&>button]:!opacity-30 [&>button]:line-through [&>button]:cursor-not-allowed [&>button]:hover:bg-transparent",
              today: "[&>button]:font-bold [&>button]:ring-1 [&>button]:ring-border",
              outside: "opacity-0 pointer-events-none",
            }}
          />
        )}

        {booking.hasFetched && !booking.calendarLoading && !booking.calendar && (
          <div className="flex flex-col items-center gap-3 py-6 text-center">
            <p className="text-sm text-muted-foreground">
              {booking.calendarError || t("calendarUnavailable")}
            </p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={booking.onRetry}
              className="gap-1.5"
            >
              <RefreshCwIcon className="size-3.5" />
              {t("retry")}
            </Button>
          </div>
        )}
      </div>

      {/* Time slot selector — only when available */}
      {!booking.calendarLoading && (booking.calendar?.time_slots.length ?? 0) > 0 && (
        <div className="flex flex-col gap-2">
          <p className="text-sm font-medium text-primary">{t("selectTimeSlot")}</p>
          <Select
            items={slotItems}
            value={booking.slotId || ""}
            onValueChange={(v) => booking.onSlotChange(v ?? "")}
            triggerClass="w-full"
          />
        </div>
      )}

      {/* Overnight toggle — only when no time slot is selected */}
      {!booking.activeSlot && (
        <Checkbox
          label={t("includesOvernight")}
          checked={booking.overnight}
          onCheckedChange={(checked) => booking.onOvernightChange(Boolean(checked))}
        />
      )}

      {/* Price summary */}
      {booking.total > 0 && (
        <div className="flex items-center justify-between border-t border-border pt-4">
          <span className="text-sm text-muted-foreground">{t("totalPrice")}</span>
          <Price currentPrice={booking.total} currency={currency} size="lg" />
        </div>
      )}

      {/* Errors */}
      {booking.submitError && (
        <div className="flex items-start gap-2 text-sm text-destructive bg-destructive/5 rounded-lg px-3 py-2.5">
          <AlertCircleIcon className="size-4 shrink-0 mt-0.5" />
          <span>{booking.submitError}</span>
        </div>
      )}

      {/* Success confirmation */}
      {booking.reservationNumber && (
        <div className="flex items-start gap-2 text-sm text-green-600 bg-green-50 dark:bg-green-950/30 dark:text-green-400 rounded-lg px-3 py-2.5">
          <CheckCircle2Icon className="size-4 shrink-0 mt-0.5" />
          <span>
            {t("unitReserved")}{" "}
            <span className="font-semibold">
              {t("reservationNumber", { number: booking.reservationNumber })}
            </span>
          </span>
        </div>
      )}

      {/* Reserve button */}
      <Button
        type="button"
        onClick={booking.submit}
        disabled={!booking.rangeValid || booking.submitting}
        className="bg-blue-3 hover:opacity-90 text-white border-transparent font-semibold w-full"
      >
        {booking.submitting ? t("bookingInProgress") : t("reserveUnit")}
      </Button>
    </Card>
  );
}

// ─── MonthNavigator ──────────────────────────────────────────────────────────

function MonthNavigator({
  month,
  locale,
  onPrev,
  onNext,
}: {
  month: Date;
  locale: string;
  onPrev: () => void;
  onNext: () => void;
}) {
  const label = month.toLocaleDateString(locale === "ar" ? "ar-SA" : "en-US", {
    month: "long",
    year: "numeric",
  });

  return (
    <div className="flex items-center justify-between mb-3 px-1">
      <button
        type="button"
        onClick={onPrev}
        aria-label="Previous month"
        className="p-1.5 rounded-lg hover:bg-muted transition-colors text-muted-foreground hover:text-primary"
      >
        <ChevronLeftIcon className="size-4" />
      </button>

      <span className="text-sm font-semibold text-primary select-none">{label}</span>

      <button
        type="button"
        onClick={onNext}
        aria-label="Next month"
        className="p-1.5 rounded-lg hover:bg-muted transition-colors text-muted-foreground hover:text-primary"
      >
        <ChevronRightIcon className="size-4" />
      </button>
    </div>
  );
}
