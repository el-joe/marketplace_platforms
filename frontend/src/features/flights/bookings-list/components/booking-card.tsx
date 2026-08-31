import Image from "next/image";
import { format } from "date-fns";
import { CalendarIcon, UsersIcon } from "lucide-react";
import { useLocale, useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { buttonVariants } from "@/src/components/ui/button";
import { Badge } from "@/src/components/ui/badge";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import { cn } from "@/src/lib/utils";
import { bookingStatusVariant } from "../../helpers/to-booking-status";
import type { TravelBooking } from "../../helpers/types";

type Props = {
  booking: TravelBooking;
};

export default function BookingCard({ booking }: Props) {
  const t = useTranslations("flights");

  const locale = useLocale() as "ar" | "en";

  console.log(booking);

  return (
    <Card className="overflow-hidden flex flex-col shadow-sm border border-border">
      <div className="relative h-52">
        <Image
          src={booking.package.cover_image}
          alt={booking.package.title?.[locale]}
          fill
          sizes="(max-width: 768px) 100vw, 400px"
          className="object-cover"
        />
      </div>

      <div className="flex flex-col gap-3 p-4 flex-1">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h3 className="font-bold text-base leading-tight text-primary truncate">
              {booking.package.title?.[locale]}
            </h3>
            <p className="text-xs text-gray mt-0.5">
              {t("myBookings.columns.bookingNumber")}: {booking.booking_number}
            </p>
          </div>
          <Badge
            variant={bookingStatusVariant(booking.status)}
            className="shrink-0"
          >
            {t(`myBookings.status.${booking.status}`)}
          </Badge>
        </div>

        <div className="flex flex-wrap gap-1.5">
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <CalendarIcon className="size-3 shrink-0" />
            {format(new Date(booking.created_at), "MMM d, yyyy")}
          </span>
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <UsersIcon className="size-3 shrink-0" />
            {booking.travelers_count} {t("myBookings.columns.travelers")}
          </span>
        </div>

        <p className="text-sm text-light">
          {t("operatedBy", { agency: booking.package.agency.name })}
        </p>

        <div className="flex items-end justify-between mt-auto pt-3 border-t border-border">
          <Price currentPrice={booking.total_price} size="lg" />
          <Link
            href={`/my-bookings/${booking.id}`}
            className={cn(
              buttonVariants({ size: "sm" }),
              "bg-blue-3 hover:bg-blue text-white border-transparent",
            )}
          >
            {t("myBookings.viewDetails")}
          </Link>
        </div>
      </div>
    </Card>
  );
}
