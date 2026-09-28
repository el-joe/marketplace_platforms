import { format } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { buttonVariants } from "@/src/components/ui/button";
import { Badge } from "@/src/components/ui/badge";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { cn } from "@/src/lib/utils";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { bookingStatusVariant, toDisplayStatus } from "../helpers/to-status-badge";
import type { UnifiedBooking } from "../helpers/types";

type Props = {
  booking: UnifiedBooking;
};

export default function UnifiedBookingCard({ booking }: Props) {
  const t = useTranslations("myBookings");

  return (
    <Card className="overflow-hidden flex flex-col shadow-sm border border-border">
      <div className="flex flex-col gap-3 p-4 flex-1">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h3 className="font-bold text-base leading-tight text-primary truncate">
              {booking.title}
            </h3>
            <p className="text-xs text-gray mt-0.5">
              {t("columns.bookingNumber")}: {booking.booking_number}
            </p>
          </div>
          <Badge variant={bookingStatusVariant(booking.status)} className="shrink-0">
            {t(`status.${toDisplayStatus(booking.status)}`)}
          </Badge>
        </div>

        <span className="inline-flex w-fit items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
          <CalendarIcon className="size-3 shrink-0" />
          {format(new Date(booking.date_from), "MMM d, yyyy")}
          {booking.date_to && booking.date_to !== booking.date_from
            ? ` – ${format(new Date(booking.date_to), "MMM d, yyyy")}`
            : ""}
        </span>

        {booking.agency_name && (
          <p className="text-sm text-light">
            {t("operatedBy", { agency: booking.agency_name })}
          </p>
        )}

        <div className="flex items-end justify-between mt-auto pt-3 border-t border-border">
          <Price
            currentPrice={booking.total_price}
            currency={booking.currency as CurrencyCode}
            size="lg"
          />
          {booking.type === "travel_package" && (
            <Link
              href={`/my-bookings/${booking.id}`}
              className={cn(
                buttonVariants({ size: "sm" }),
                "bg-blue-3 hover:bg-blue text-white border-transparent",
              )}
            >
              {t("viewDetails")}
            </Link>
          )}
        </div>
      </div>
    </Card>
  );
}
