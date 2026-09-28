"use client";

import { useTranslations } from "next-intl";
import { cn } from "@/src/lib/utils";
import { BOOKING_TABS } from "../helpers/constants";
import { useTabs } from "../helpers/use-tabs";
import UnifiedBookingCard from "./unified-booking-card";
import type { UnifiedBooking } from "../helpers/types";

type Props = {
  data: UnifiedBooking[];
};

export default function BookingsTabs({ data }: Props) {
  const t = useTranslations("myBookings");
  const { tab, setTab, filtered } = useTabs(data);

  return (
    <div>
      <div className="flex flex-wrap gap-2 border-b border-border">
        {BOOKING_TABS.map((type) => (
          <button
            key={type}
            type="button"
            onClick={() => setTab(type)}
            className={cn(
              "px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors",
              tab === type
                ? "border-blue-3 text-blue-3"
                : "border-transparent text-gray hover:text-primary",
            )}
          >
            {t(`tabs.${type}`)}
          </button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <p className="mt-6 text-center text-sm text-gray">{t("noResults")}</p>
      ) : (
        <div className="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {filtered.map((booking) => (
            <UnifiedBookingCard key={`${booking.type}-${booking.id}`} booking={booking} />
          ))}
        </div>
      )}
    </div>
  );
}
