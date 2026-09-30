"use client";

import { useEffect, useReducer } from "react";
import { useTranslations } from "next-intl";
import type { DateRange } from "react-day-picker";
import { ApiRequestError } from "@/src/lib/utils";
import { getBookableUnitCalendar } from "../../api/bookable-units.actions";
import type {
  BookableUnitCalendar,
  BookableUnitDay,
  BookableUnitSummary,
} from "../../helpers/types";

// ─── helpers ────────────────────────────────────────────────────────────────

export function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

export function fromDateStr(s: string): Date {
  return new Date(s + "T00:00:00");
}

function monthKey(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

function todayStr(): string {
  return new Date().toISOString().slice(0, 10);
}

// ─── state & actions ─────────────────────────────────────────────────────────

type State = {
  unitId: string;
  month: Date;
  calendar: BookableUnitCalendar | null;
  calendarLoading: boolean;
  calendarError: string | null;
  hasFetched: boolean;
  dateFrom: string | null;
  dateTo: string | null;
  slotId: string;
  overnight: boolean;
};

type Action =
  | { type: "SET_UNIT"; unitId: string }
  | { type: "SET_MONTH"; month: Date }
  | { type: "CALENDAR_LOADING" }
  | { type: "CALENDAR_OK"; calendar: BookableUnitCalendar }
  | { type: "CALENDAR_ERR"; error: string }
  | { type: "SET_RANGE"; from: string | null; to: string | null }
  | { type: "SET_SLOT"; slotId: string }
  | { type: "SET_OVERNIGHT"; overnight: boolean }
  | { type: "RETRY" };

function resetSelection(s: State): Partial<State> {
  return { dateFrom: null, dateTo: null, slotId: "" };
}

function reducer(state: State, action: Action): State {
  switch (action.type) {
    case "SET_UNIT":
      return {
        ...state,
        unitId: action.unitId,
        calendar: null,
        calendarLoading: true,
        calendarError: null,
        hasFetched: false,
        ...resetSelection(state),
      };
    case "SET_MONTH":
      return {
        ...state,
        month: action.month,
        calendar: null,
        calendarLoading: true,
        calendarError: null,
        ...resetSelection(state),
      };
    case "CALENDAR_LOADING":
      return { ...state, calendarLoading: true, calendarError: null };
    case "CALENDAR_OK":
      return { ...state, calendar: action.calendar, calendarLoading: false, calendarError: null, hasFetched: true };
    case "CALENDAR_ERR":
      return { ...state, calendar: null, calendarLoading: false, calendarError: action.error, hasFetched: true };
    case "SET_RANGE":
      return { ...state, dateFrom: action.from, dateTo: action.to };
    case "SET_SLOT":
      return { ...state, slotId: action.slotId, dateTo: null };
    case "SET_OVERNIGHT":
      return { ...state, overnight: action.overnight };
    case "RETRY":
      return { ...state, calendarLoading: true, calendarError: null, hasFetched: false };
    default:
      return state;
  }
}

// ─── hook ────────────────────────────────────────────────────────────────────

export function useUnitBooking(units: BookableUnitSummary[]) {
  const t = useTranslations("flights.packageDetails");

  const [state, dispatch] = useReducer(reducer, {
    unitId: units[0]?.id ?? "",
    month: new Date(),
    calendar: null,
    calendarLoading: Boolean(units[0]?.id),
    calendarError: null,
    hasFetched: false,
    dateFrom: null,
    dateTo: null,
    slotId: "",
    overnight: true,
  });

  const key = monthKey(state.month);

  useEffect(() => {
    if (!state.unitId) {
      dispatch({ type: "CALENDAR_ERR", error: "" });
      return;
    }
    let cancelled = false;
    dispatch({ type: "CALENDAR_LOADING" });
    getBookableUnitCalendar(state.unitId, key)
      .then((c) => { if (!cancelled) dispatch({ type: "CALENDAR_OK", calendar: c }); })
      .catch((err) => {
        if (!cancelled) {
          const msg =
            err instanceof ApiRequestError || err instanceof Error
              ? err.message
              : t("calendarUnavailable");
          dispatch({ type: "CALENDAR_ERR", error: msg });
        }
      });
    return () => { cancelled = true; };
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.unitId, key]);

  // derived
  const byDate = new Map<string, BookableUnitDay>(
    (state.calendar?.days ?? []).map((d: BookableUnitDay) => [d.date, d]),
  );

  const activeSlot =
    (state.calendar?.time_slots ?? []).find(
      (s: BookableUnitCalendar["time_slots"][number]) => s.id === state.slotId,
    ) ?? null;

  const effectiveTo = activeSlot ? state.dateFrom : (state.dateTo ?? state.dateFrom);

  const selectedDays = (() => {
    if (!state.dateFrom || !effectiveTo) return [];
    const out: BookableUnitDay[] = [];
    for (let d = state.dateFrom; d <= effectiveTo; ) {
      const day = byDate.get(d);
      if (!day) return [];
      out.push(day);
      const next = new Date(d + "T00:00:00");
      next.setDate(next.getDate() + 1);
      d = toDateStr(next);
    }
    return out;
  })();

  const rangeValid = selectedDays.length > 0 && selectedDays.every((d) => d.is_available);

  const unitTotal = activeSlot
    ? activeSlot.price
    : selectedDays.reduce(
        (sum, d) =>
          sum + ((state.overnight ? d.price_with_overnight : d.price_day_only) ?? 0),
        0,
      );

  const selectedRange: DateRange | undefined = state.dateFrom
    ? {
        from: fromDateStr(state.dateFrom),
        to: effectiveTo ? fromDateStr(effectiveTo) : undefined,
      }
    : undefined;

  /** Payload to send along with the travel booking POST. Null when no days selected. */
  const unitDaysPayload =
    state.unitId && rangeValid
      ? {
          unit_id: state.unitId,
          unit_days: selectedDays.map((d) => ({
            date: d.date,
            ...(activeSlot
              ? { time_slot_id: activeSlot.id }
              : { includes_overnight: state.overnight }),
          })),
        }
      : null;

  function isDisabled(date: Date): boolean {
    const d = toDateStr(date);
    const day = byDate.get(d);
    return !day || !day.is_available || d < todayStr();
  }

  function onRangeSelect(range: DateRange | undefined) {
    dispatch({
      type: "SET_RANGE",
      from: range?.from ? toDateStr(range.from) : null,
      to: range?.to ? toDateStr(range.to) : null,
    });
  }

  function onUnitChange(unitId: string) {
    dispatch({ type: "SET_UNIT", unitId });
  }

  function onMonthChange(month: Date) {
    dispatch({ type: "SET_MONTH", month });
  }

  function onSlotChange(slotId: string) {
    dispatch({ type: "SET_SLOT", slotId });
  }

  function onOvernightChange(overnight: boolean) {
    dispatch({ type: "SET_OVERNIGHT", overnight });
  }

  function onRetry() {
    dispatch({ type: "RETRY" });
  }

  return {
    // state
    unitId: state.unitId,
    month: state.month,
    calendar: state.calendar,
    calendarLoading: state.calendarLoading,
    calendarError: state.calendarError,
    hasFetched: state.hasFetched,
    slotId: state.slotId,
    overnight: state.overnight,
    // derived
    byDate,
    activeSlot,
    selectedRange,
    rangeValid,
    unitTotal,
    unitDaysPayload,
    // actions
    isDisabled,
    onRangeSelect,
    onUnitChange,
    onMonthChange,
    onSlotChange,
    onOvernightChange,
    onRetry,
  };
}
