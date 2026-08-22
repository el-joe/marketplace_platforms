"use client";

import { useMemo, useState } from "react";
import { ALL } from "./constants";
import type { TravelBooking } from "../../helpers/types";

export function useBookingsList(data: TravelBooking[]) {
  const [status, setStatus] = useState<string>(ALL);

  const filtered = useMemo(
    () => data.filter((row) => status === ALL || row.status === status),
    [data, status],
  );

  return { status, setStatus, filtered };
}
