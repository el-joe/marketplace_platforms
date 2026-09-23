"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { AlertCircleIcon, ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
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

function monthKey(d: Date) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

export default function BookableUnitsSection({ units, currency }: Props) {
  const t = useTranslations("flights.packageDetails");
  const [unitId, setUnitId] = useState(units[0]?.id ?? "");
  const [month, setMonth] = useState(() => new Date());
  const [calendar, setCalendar] = useState<BookableUnitCalendar | null>(null);
  const [loading, setLoading] = useState(false);
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);
  const [overnight, setOvernight] = useState(true);
  const [slotId, setSlotId] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const key = monthKey(month);

  useEffect(() => {
    if (!unitId) return;
    let cancelled = false;
    setLoading(true);
    getBookableUnitCalendar(unitId, key)
      .then((c) => !cancelled && setCalendar(c))
      .catch(() => !cancelled && setCalendar(null))
      .finally(() => !cancelled && setLoading(false));
    return () => {
      cancelled = true;
    };
  }, [unitId, key]);

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
      d = `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, "0")}-${String(n.getDate()).padStart(2, "0")}`;
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

  function pick(date: string) {
    setDone(false);
    if (!from || to || slot || date < from) {
      setFrom(date);
      setTo(null);
    } else {
      setTo(date);
    }
  }

  async function submit() {
    if (!from || !end || !rangeValid) return;
    setBusy(true);
    setError(null);
    try {
      await reserveBookableUnit(unitId, {
        date_from: from,
        date_to: end,
        includes_overnight: slot ? false : overnight,
        ...(slot ? { time_slot_id: slot.id } : {}),
      });
      setDone(true);
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

  const firstWeekday = new Date(month.getFullYear(), month.getMonth(), 1).getDay();

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

        <div className="flex items-center justify-between">
          <button
            type="button"
            aria-label={t("prevMonth")}
            onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))}
            className="p-1"
          >
            <ChevronLeftIcon className="size-5 rtl:rotate-180" />
          </button>
          <span className="font-bold text-primary">{key}</span>
          <button
            type="button"
            aria-label={t("nextMonth")}
            onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))}
            className="p-1"
          >
            <ChevronRightIcon className="size-5 rtl:rotate-180" />
          </button>
        </div>

        <div className={`grid grid-cols-7 gap-1 text-center ${loading ? "opacity-50" : ""}`}>
          {Array.from({ length: firstWeekday }).map((_, i) => (
            <span key={`b${i}`} />
          ))}
          {calendar?.days.map((d) => {
            const inRange = from && end && d.date >= from && d.date <= end;
            const past = d.date < new Date().toISOString().slice(0, 10);
            const disabled = !d.is_available || past;
            return (
              <button
                key={d.date}
                type="button"
                disabled={disabled}
                onClick={() => pick(d.date)}
                className={`rounded-lg py-1.5 text-xs flex flex-col items-center ${
                  inRange
                    ? "bg-blue-3 text-white"
                    : disabled
                      ? "bg-gray-2/40 text-light line-through cursor-not-allowed"
                      : "border border-border hover:bg-gray-2/40"
                }`}
              >
                <span className="font-bold">{Number(d.date.slice(8))}</span>
                {!disabled && (d.price_day_only ?? d.price_with_overnight) !== null && (
                  <span className="text-[9px] opacity-80">
                    {d.price_day_only ?? d.price_with_overnight}
                  </span>
                )}
              </button>
            );
          })}
        </div>

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

        <div className="flex items-center justify-between border-t border-border pt-4">
          <span className="text-sm text-gray">{t("totalPrice")}</span>
          <Price currentPrice={total} currency={currency} size="lg" />
        </div>

        {error && (
          <div className="flex items-start gap-2 text-sm text-red bg-red/5 rounded-lg px-3 py-2">
            <AlertCircleIcon className="size-4 shrink-0 mt-0.5" />
            <span>{error}</span>
          </div>
        )}
        {done && <p className="text-sm text-green">{t("unitReserved")}</p>}

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
