import { fetchInstance } from "@/src/lib/utils";
import type { ApiEnvelope, BookableUnitCalendar } from "../helpers/types";

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
