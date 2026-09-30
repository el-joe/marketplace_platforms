"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { AlertCircleIcon } from "lucide-react";
import { DayPicker, type DateRange, type DayButtonProps } from "react-day-picker";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import { ApiRequestError } from "@/src/lib/utils";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import {
  getBookableUnitCalendar,
  reserveBookableUnit,
} from "../../api/bookable-units.actions";
import type {
  BookableUnitCalendar,
  BookableUnitSummary,
} from "../../helpers/types";

type Props = {
  units: BookableUnitSummary[];
  currency: CurrencyCode;
};

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function fromDateStr(s: string): Date {
  return new Date(s + "T00:00:00");
}

function monthKey(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

export default function BookableUnitsSection({ units, currency }: Props) {
  const t = useTranslations("flights.packageDetails");
  const [unitId, setUnitId] = useState(units[0]?.id ?? "");
  const [month, setMonth] = useState(() => new Date());
  const [calendar, setCalendar] = useState<BookableUnitCalendar | null>(null);
  const [loading, setLoading] = useState(true);
  const [hasFetched, setHasFetched] = useState(false);
  const [calendarError, setCalendarError] = useState<string | null>(null);
  const [retryKey, setRetryKey] = useState(0);
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [overnight, setOvernight] = useState(true);
  const [slotId, setSlotId] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reservationNumber, setReservationNumber] = useState<string | null>(null);

  const key = monthKey(month);

  useEffect(() => {
    if (!unitId) {
      setHasFetched(true);
      setLoading(false);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setCalendarError(null);
    getBookableUnitCalendar(unitId, key)
      .then((c) => { if (!cancelled) { setCalendar(c); setCalendarError(null); } })
      .catch((err) => {
        if (!cancelled) {
          setCalendar(null);
          setCalendarError(
            err instanceof ApiRequestError || err instanceof Error
              ? err.message
              : t("calendarUnavailable"),
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
          setHasFetched(true);
        }
      });
    return () => { cancelled = true; };
  }, [unitId, key, retryKey]);

  if (units.length === 0) return null;

  const byDate = new Map(calendar?.days.map((d) => [d.date, d]));
  const slot = calendar?.time_slots.find((s) => s.id === slotId);
  const end = slot ? from : (to ?? from);

  const selectedDays = (() => {
    if (!from || !end) return [];
    const out = [];
    for (let d = from; d <= end; ) {
      const day = byDate.get(d);
      if (!day) return [];
      out.push(day);
      const n = new Date(d + "T00:00:00");
      n.setDate(n.getDate() + 1);
      d = toDateStr(n);
    }
    return out;
  })();
  const rangeValid = selectedDays.length > 0 && selectedDays.every((d) => d.is_available);
  const total = slot
    ? slot.price
    : selectedDays.reduce(
        (sum, d) =>
          sum + ((overnight ? d.price_with_overnight : d.price_day_only) ?? 0),
        0,
      );

  function handleSelect(range: DateRange | undefined) {
    setReservationNumber(null);
    if (!range) { setFrom(null); setTo(null); return; }
    setFrom(range.from ? toDateStr(range.from) : null);
    setTo(range.to ? toDateStr(range.to) : null);
  }

  function isDisabled(date: Date): boolean {
    const d = toDateStr(date);
    const today = new Date().toISOString().slice(0, 10);
    const day = byDate.get(d);
    return !day || !day.is_available || d < today;
  }

  async function submit() {
    if (!from || !end || !rangeValid) return;
    setBusy(true);
    setError(null);
    try {
      const reservation = await reserveBookableUnit(unitId, {
        date_from: from,
        date_to: end,
        includes_overnight: slot ? false : overnight,
        ...(slot ? { time_slot_id: slot.id } : {}),
      });
      setReservationNumber(reservation.reservation_number);
      setFrom(null);
      setTo(null);
      setCalendar(await getBookableUnitCalendar(unitId, key));
    } catch (err) {
      setError(
        err instanceof ApiRequestError || err instanceof Error
          ? err.message
          : t("bookingError"),
      );
    } finally {
      setBusy(false);
    }
  }

  const selectedRange: DateRange | undefined =
    from
      ? { from: fromDateStr(from), to: (to ?? from) ? fromDateStr(to ?? from!) : undefined }
      : undefined;

  return (
    <section>
      <h2 className="text-2xl font-bold text-primary mb-8">{t("unitsTitle")}</h2>
      <Card className="border border-border shadow-sm p-6 flex flex-col gap-4">
        <select
          value={unitId}
          onChange={(e) => {
            setUnitId(e.target.value);
            setFrom(null);
            setTo(null);
            setSlotId("");
          }}
          className="border border-border rounded-lg px-3 py-2 text-sm bg-transparent"
        >
          {units.map((u) => (
            <option key={u.id} value={u.id}>
              {u.name} — {t("unitCapacity", { count: u.capacity })}
            </option>
          ))}
        </select>

        {(() => {
          const selectedUnit = units.find((u) => u.id === unitId);
          return selectedUnit?.primary_photo_url ? (
            <img
              src={selectedUnit.primary_photo_url}
              alt={selectedUnit.name}
              className="w-full h-48 object-cover rounded-lg"
            />
          ) : (
            <div className="w-full h-48 rounded-lg bg-gray-2/40 flex items-center justify-center text-light text-sm">
              {t("noPhoto")}
            </div>
          );
        })()}

        <div className={loading ? "opacity-50 pointer-events-none" : ""}>
          {loading && (
            <div className="grid grid-cols-7 gap-1 mb-2">
              {Array.from({ length: 35 }).map((_, i) => (
                <div key={i} className="rounded-lg py-8 bg-gray-2/40 animate-pulse" />
              ))}
            </div>
          )}
          {!loading && calendar && (
            <DayPicker
              mode="range"
              month={month}
              onMonthChange={(m) => {
                setMonth(m);
                setFrom(null);
                setTo(null);
              }}
              selected={selectedRange}
              onSelect={handleSelect}
              disabled={isDisabled}
              showOutsideDays={false}
              components={{
                DayButton: (props: DayButtonProps) => {
                  const dateStr = toDateStr(props.day.date);
                  const dayData = byDate.get(dateStr);
                  const price = dayData?.price_day_only ?? dayData?.price_with_overnight;
                  return (
                    <button {...props} className={[props.className, "flex flex-col items-center gap-0.5 py-1 w-full"].filter(Boolean).join(" ")}>
                      <span>{props.day.date.getDate()}</span>
                      {price !== null && price !== undefined && dayData?.is_available && (
                        <span className="text-[9px] opacity-70 leading-none">{price}</span>
                      )}
                    </button>
                  );
                },
              }}
              classNames={{
                root: "w-full",
                months: "relative w-full",
                month: "w-full",
                month_caption: "flex justify-center items-center h-9 mb-2 font-bold text-primary",
                nav: "absolute inset-x-0 top-0 flex justify-between items-center h-9 px-1 z-10",
                button_previous: "p-1.5 hover:bg-gray-2/40 rounded-lg cursor-pointer",
                button_next: "p-1.5 hover:bg-gray-2/40 rounded-lg cursor-pointer",
                month_grid: "w-full border-collapse",
                weekdays: "flex",
                weekday: "flex-1 text-center text-xs text-light pb-2 select-none",
                week: "flex",
                day: "flex-1 aspect-square p-0.5",
                day_button: "w-full h-full rounded-lg text-xs hover:bg-gray-2/40 transition-colors",
                selected: "",
                range_start: "[&>button]:!bg-blue-3 [&>button]:!text-white",
                range_end: "[&>button]:!bg-blue-3 [&>button]:!text-white",
                range_middle: "[&>button]:!bg-blue-3/20 [&>button]:rounded-none",
                disabled: "[&>button]:!opacity-35 [&>button]:line-through [&>button]:cursor-not-allowed [&>button]:hover:bg-transparent",
                today: "[&>button]:font-bold [&>button]:underline",
                outside: "opacity-0 pointer-events-none",
              }}
            />
          )}
        </div>

        {hasFetched && !loading && !calendar && (
          <div className="flex flex-col items-center gap-3 py-4">
            <p className="text-sm text-center text-light">
              {calendarError ?? t("calendarUnavailable")}
            </p>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setRetryKey((k) => k + 1)}
            >
              {t("retry") ?? "Retry"}
            </Button>
          </div>
        )}

        {calendar && calendar.time_slots.length > 0 && (
          <select
            value={slotId}
            onChange={(e) => {
              setSlotId(e.target.value);
              setTo(null);
            }}
            className="border border-border rounded-lg px-3 py-2 text-sm bg-transparent"
          >
            <option value="">{t("fullDay")}</option>
            {calendar.time_slots.map((s) => (
              <option key={s.id} value={s.id}>
                {t(`slot_${s.slot_type}`)} {s.starts_at.slice(0, 5)}–{s.ends_at.slice(0, 5)}
              </option>
            ))}
          </select>
        )}

        {!slot && (
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={overnight}
              onChange={(e) => setOvernight(e.target.checked)}
            />
            {t("includesOvernight")}
          </label>
        )}

        {(total > 0 || from) && (
          <div className="flex items-center justify-between border-t border-border pt-4">
            <span className="text-sm text-gray">{t("totalPrice")}</span>
            <Price currentPrice={total} currency={currency} size="lg" />
          </div>
        )}

        {error && (
          <div className="flex items-start gap-2 text-sm text-red bg-red/5 rounded-lg px-3 py-2">
            <AlertCircleIcon className="size-4 shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}
        {reservationNumber && (
          <p className="text-sm text-green">
            {t("unitReserved")} {t("reservationNumber", { number: reservationNumber })}
          </p>
        )}

        <Button
          type="button"
          onClick={submit}
          disabled={!rangeValid || busy}
          className="bg-blue-3 hover:opacity-90 text-white border-transparent font-bold w-full"
        >
          {busy ? t("bookingInProgress") : t("reserveUnit")}
        </Button>
      </Card>
    </section>
  );
}
