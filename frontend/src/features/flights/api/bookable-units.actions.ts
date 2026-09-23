import { fetchInstance } from "@/src/lib/utils";
import type {
  ApiEnvelope,
  BookableUnitCalendar,
  BookableUnitReservation,
} from "../helpers/types";

/** GET /bookable-units/:unit/calendar?month=YYYY-MM — public month calendar. */
export async function getBookableUnitCalendar(
  unitId: string,
  month: string,
): Promise<BookableUnitCalendar> {
  const envelope = await fetchInstance<ApiEnvelope<BookableUnitCalendar>>(
    `/bookable-units/${unitId}/calendar?month=${month}`,
  );
  return envelope.data;
}

/** POST /bookable-units/:unit/reservations — requires auth:customer. */
export async function reserveBookableUnit(
  unitId: string,
  payload: {
    date_from: string;
    date_to: string;
    includes_overnight: boolean;
    time_slot_id?: string;
  },
): Promise<BookableUnitReservation> {
  const envelope = await fetchInstance<ApiEnvelope<BookableUnitReservation>>(
    `/bookable-units/${unitId}/reservations`,
    { method: "POST", body: JSON.stringify(payload) },
  );
  return envelope.data;
}
