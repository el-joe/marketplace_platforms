"use client";

import { useEffect, useReducer, useCallback } from "react";
import { useTranslations } from "next-intl";
import type { DateRange } from "react-day-picker";
import { ApiRequestError } from "@/src/lib/utils";
import {
  getBookableUnitCalendar,
  reserveBookableUnit,
} from "../../api/bookable-units.actions";
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
  submitting: boolean;
  submitError: string | null;
  reservationNumber: string | null;
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
  | { type: "SUBMIT_START" }
  | { type: "SUBMIT_OK"; reservationNumber: string; calendar: BookableUnitCalendar }
  | { type: "SUBMIT_ERR"; error: string }
  | { type: "RETRY" };

function resetSelection(s: State): Partial<State> {
  return { dateFrom: null, dateTo: null, slotId: "", submitError: null, reservationNumber: null };
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
      return { ...state, dateFrom: action.from, dateTo: action.to, submitError: null, reservationNumber: null };
    case "SET_SLOT":
      return { ...state, slotId: action.slotId, dateTo: null };
    case "SET_OVERNIGHT":
      return { ...state, overnight: action.overnight };
    case "SUBMIT_START":
      return { ...state, submitting: true, submitError: null };
    case "SUBMIT_OK":
      return {
        ...state,
        submitting: false,
        reservationNumber: action.reservationNumber,
        calendar: action.calendar,
        ...resetSelection(state),
      };
    case "SUBMIT_ERR":
      return { ...state, submitting: false, submitError: action.error };
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
    submitting: false,
    submitError: null,
    reservationNumber: null,
  });

  const key = monthKey(state.month);

  // fetch calendar whenever unit or month changes
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
  // when a time slot is selected it's a single-day booking
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

  const total = activeSlot
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

  const submit = useCallback(async () => {
    if (!state.dateFrom || !effectiveTo || !rangeValid) return;
    dispatch({ type: "SUBMIT_START" });
    try {
      const reservation = await reserveBookableUnit(state.unitId, {
        date_from: state.dateFrom,
        date_to: effectiveTo,
        includes_overnight: activeSlot ? false : state.overnight,
        ...(activeSlot ? { time_slot_id: activeSlot.id } : {}),
      });
      const refreshed = await getBookableUnitCalendar(state.unitId, key);
      dispatch({ type: "SUBMIT_OK", reservationNumber: reservation.reservation_number, calendar: refreshed });
    } catch (err) {
      const msg =
        err instanceof ApiRequestError || err instanceof Error
          ? err.message
          : t("bookingError");
      dispatch({ type: "SUBMIT_ERR", error: msg });
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state.dateFrom, effectiveTo, rangeValid, state.unitId, activeSlot, state.overnight, key]);

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
    submitting: state.submitting,
    submitError: state.submitError,
    reservationNumber: state.reservationNumber,
    // derived
    byDate,
    activeSlot,
    selectedRange,
    rangeValid,
    total,
    // actions
    isDisabled,
    onRangeSelect,
    onUnitChange,
    onMonthChange,
    onSlotChange,
    onOvernightChange,
    onRetry,
    submit,
  };
}
