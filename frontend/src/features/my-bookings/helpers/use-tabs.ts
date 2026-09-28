"use client";

import { useMemo, useState } from "react";
import { BOOKING_TABS } from "./constants";
import type { UnifiedBooking, UnifiedBookingType } from "./types";

export function useTabs(data: UnifiedBooking[]) {
  const [tab, setTab] = useState<UnifiedBookingType>(BOOKING_TABS[0]);

  const filtered = useMemo(
    () => data.filter((booking) => booking.type === tab),
    [data, tab],
  );

  return { tab, setTab, filtered };
}
