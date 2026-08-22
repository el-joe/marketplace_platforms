import Image from "next/image";
import { format } from "date-fns";
import { getTranslations } from "next-intl/server";
import { CheckIcon, XIcon } from "lucide-react";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import { Separator } from "@/src/components/ui/separator";
import Price from "@/src/components/shared/Price";
import CancelBookingDialog from "./components/cancel-booking-dialog";
import {
  bookingStatusVariant,
  isCancellableStatus,
} from "../helpers/to-booking-status";
import type { TravelBookingDetail } from "../helpers/types";

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <p className="text-xs text-gray uppercase tracking-wide mb-1">{label}</p>
      <div className="text-sm font-semibold text-primary">{value}</div>
    </div>
  );
}

type Props = {
  booking: TravelBookingDetail;
};

export default async function BookingDetails({ booking }: Props) {
  const t = await getTranslations("flights");

  return (
    <div>
      <Breadcrumb
        list={[
          { label: t("myBookings.title"), href: "/my-bookings" },
          { label: booking.booking_number, href: "" },
        ]}
      />

      <div className="mt-3 flex items-center justify-between">
        <h1 className="text-[28px] font-bold text-primary">
          {booking.package.title}
        </h1>

        {isCancellableStatus(booking.status) && (
          <CancelBookingDialog bookingId={booking.id} />
        )}
      </div>

      <div className="mt-6 grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 items-start">
        <Card className="border border-border p-6 flex flex-col gap-4 lg:sticky lg:top-6">
          <div>
            <p className="text-xs text-gray uppercase tracking-wide mb-1">
              {t("myBookings.columns.bookingNumber")}
            </p>
            <h2 className="text-lg font-bold text-primary">
              {booking.booking_number}
            </h2>
          </div>

          <Badge variant={bookingStatusVariant(booking.status)} className="w-fit">
            {t(`myBookings.status.${booking.status}`)}
          </Badge>

          <Separator />

          <InfoRow
            label={t("myBookings.columns.travelers")}
            value={booking.travelers_count}
          />
          <InfoRow
            label={t("myBookings.totalPrice")}
            value={
              <Price
                currentPrice={booking.total_price / 100}
                size="sm"
              />
            }
          />
          <InfoRow
            label={t("myBookings.passportUploaded")}
            value={
              booking.passport_uploaded ? (
                <span className="flex items-center gap-1.5 text-green">
                  <CheckIcon className="size-4" /> {t("myBookings.yes")}
                </span>
              ) : (
                <span className="flex items-center gap-1.5 text-gray">
                  <XIcon className="size-4" /> {t("myBookings.no")}
                </span>
              )
            }
          />

          <Separator />

          <InfoRow
            label={t("myBookings.columns.created")}
            value={format(new Date(booking.created_at), "MMM d, yyyy 'at' h:mm a")}
          />
          {booking.contract_signed_at && (
            <InfoRow
              label={t("myBookings.contractSignedAt")}
              value={format(
                new Date(booking.contract_signed_at),
                "MMM d, yyyy 'at' h:mm a",
              )}
            />
          )}
        </Card>

        <Card className="border border-border overflow-hidden p-0">
          <div className="relative h-56 w-full">
            <Image
              src={booking.package.cover_image}
              alt={booking.package.title}
              fill
              className="object-cover"
            />
          </div>

          <div className="p-6">
            <h2 className="text-lg font-bold text-primary">
              {booking.package.title}
            </h2>
            <p className="text-sm text-gray mt-1">
              {t("myBookings.operatedBy", {
                agency: booking.package.agency.name,
              })}
            </p>

            <Separator className="my-4" />

            <div className="flex items-center justify-between text-sm">
              <p className="text-gray">{t("myBookings.pricePerPerson")}</p>
              <Price
                currentPrice={booking.package.price / 100}
                size="xs"
              />
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}
