"use client";

import Image from "next/image";
import { format } from "date-fns";
import { CheckIcon, XIcon, CalendarIcon, UsersIcon, BedDoubleIcon, MoonIcon, SunIcon, ClockIcon } from "lucide-react";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import Card from "@/src/components/shared/Card";
import { Badge } from "@/src/components/ui/badge";
import { Separator } from "@/src/components/ui/separator";
import Price from "@/src/components/shared/Price";
import CancelBookingDialog from "./components/cancel-booking-dialog";
import PassportUpload from "./components/passport-upload";
import {
  bookingStatusVariant,
  isCancellableStatus,
} from "../helpers/to-booking-status";
import type { TravelBookingDetail, BookingUnitDaySummary } from "../helpers/types";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <p className="text-xs text-gray uppercase tracking-wide mb-1">{label}</p>
      <div className="text-sm font-semibold text-primary">{value}</div>
    </div>
  );
}

function formatPrice(cents: number, currency: string) {
  return (cents / 100).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }) + " " + currency;
}

function slotTypeIcon(slotType: string) {
  if (slotType === "morning") return <SunIcon className="size-3.5" />;
  if (slotType === "evening") return <MoonIcon className="size-3.5" />;
  return <ClockIcon className="size-3.5" />;
}

type Props = {
  booking: TravelBookingDetail;
};

export default function BookingDetails({ booking }: Props) {
  const t = useTranslations("flights");
  const locale = useLocale() as "en" | "ar";
  const currency = booking.currency ?? booking.package.currency;

  const packagePrice = booking.package.price; // cents per person
  const travelersCount = booking.travelers_count;
  const packageSubtotal = packagePrice * travelersCount;
  const unitDaysTotal = booking.unit_days_total ?? 0;
  const grandTotal = booking.total_price;

  const hasUnit = !!booking.bookable_unit;
  const hasUnitDays = booking.unit_days && booking.unit_days.length > 0;

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
          {booking.package.title?.[locale]}
        </h1>

        {isCancellableStatus(booking.status) && (
          <CancelBookingDialog bookingId={booking.id} />
        )}
      </div>

      {/* Trip dates banner */}
      {(booking.package.departure_date || booking.package.return_date) && (
        <div className="mt-4 flex flex-wrap items-center gap-4 rounded-lg bg-accent/40 px-4 py-3 text-sm">
          <span className="flex items-center gap-1.5 text-primary font-medium">
            <CalendarIcon className="size-4 text-gray" />
            {booking.package.departure_date
              ? format(new Date(booking.package.departure_date), "MMM d, yyyy")
              : "—"}
            {" → "}
            {booking.package.return_date
              ? format(new Date(booking.package.return_date), "MMM d, yyyy")
              : "—"}
          </span>
          {booking.package.duration_days != null && (
            <span className="text-gray">
              {booking.package.duration_days}D / {booking.package.duration_nights}N
            </span>
          )}
        </div>
      )}

      <div className="mt-6 grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 items-start">
        {/* ── Sidebar ── */}
        <Card className="border border-border p-6 flex flex-col gap-4 lg:sticky lg:top-6">
          <div>
            <p className="text-xs text-gray uppercase tracking-wide mb-1">
              {t("myBookings.columns.bookingNumber")}
            </p>
            <h2 className="text-lg font-bold text-primary">
              {booking.booking_number}
            </h2>
          </div>

          <Badge
            variant={bookingStatusVariant(booking.status)}
            className="w-fit"
          >
            {t(`myBookings.status.${booking.status}`)}
          </Badge>

          <Separator />

          <InfoRow
            label={t("myBookings.columns.travelers")}
            value={
              <span className="flex items-center gap-1.5">
                <UsersIcon className="size-3.5 text-gray" />
                {booking.travelers_count}
              </span>
            }
          />

          <Separator />

          {/* Price breakdown */}
          <div className="flex flex-col gap-2 text-sm">
            <p className="text-xs text-gray uppercase tracking-wide">
              {t("myBookings.totalPrice")}
            </p>
            <div className="flex justify-between text-primary/70">
              <span>{t("myBookings.pricePerPerson")} × {travelersCount}</span>
              <span>{formatPrice(packageSubtotal, currency)}</span>
            </div>
            {unitDaysTotal > 0 && (
              <div className="flex justify-between text-primary/70">
                <span>Unit days</span>
                <span>{formatPrice(unitDaysTotal, currency)}</span>
              </div>
            )}
            <Separator />
            <div className="flex justify-between font-bold text-primary">
              <span>Total</span>
              <Price currentPrice={grandTotal} size="sm" />
            </div>
          </div>

          <Separator />

          <InfoRow
            label={t("myBookings.passportUploaded")}
            value={
              booking.passport_uploaded ? (
                <span className="flex items-center gap-1.5 text-green">
                  <CheckIcon className="size-4" /> {t("myBookings.yes")}
                </span>
              ) : booking.status === "pending_documents" ? (
                <PassportUpload bookingId={booking.id} />
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
            value={format(
              new Date(booking.created_at),
              "MMM d, yyyy 'at' h:mm a",
            )}
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

        {/* ── Main content ── */}
        <div className="flex flex-col gap-6">
          {/* Package card */}
          <Card className="border border-border overflow-hidden p-0">
            <div className="relative h-56 w-full">
              <Image
                src={booking.package.cover_image}
                alt={booking.package.title?.[locale]}
                fill
                className="object-cover"
              />
            </div>

            <div className="p-6">
              <h2 className="text-lg font-bold text-primary">
                {booking.package.title?.[locale]}
              </h2>
              <p className="text-sm text-gray mt-1">
                {t("myBookings.operatedBy", {
                  agency: booking.package.agency.name,
                })}
              </p>

              <Separator className="my-4" />

              <div className="flex items-center justify-between text-sm">
                <p className="text-gray">{t("myBookings.pricePerPerson")}</p>
                <Price currentPrice={booking.package.price} size="xs" />
              </div>
            </div>
          </Card>

          {/* Bookable unit card */}
          {hasUnit && (
            <Card className="border border-border overflow-hidden p-0">
              {booking.bookable_unit!.primary_photo_url && (
                <div className="relative h-40 w-full">
                  <Image
                    src={booking.bookable_unit!.primary_photo_url}
                    alt={booking.bookable_unit!.name}
                    fill
                    className="object-cover"
                  />
                </div>
              )}
              <div className="p-6">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h3 className="text-base font-bold text-primary flex items-center gap-2">
                      <BedDoubleIcon className="size-4 text-gray" />
                      {locale === "ar" && booking.bookable_unit!.name_ar
                        ? booking.bookable_unit!.name_ar
                        : booking.bookable_unit!.name}
                    </h3>
                    <p className="text-sm text-gray mt-0.5 capitalize">
                      {booking.bookable_unit!.type.replace("_", " ")}
                    </p>
                  </div>
                  <Badge variant="outline" className="shrink-0">
                    <UsersIcon className="size-3 mr-1" />
                    Up to {booking.bookable_unit!.capacity}
                  </Badge>
                </div>
              </div>
            </Card>
          )}

          {/* Unit days breakdown */}
          {hasUnitDays && (
            <Card className="border border-border p-6">
              <h3 className="text-base font-bold text-primary mb-4">Unit Days Breakdown</h3>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left text-xs text-gray uppercase tracking-wide pb-2 font-medium">Date</th>
                      <th className="text-left text-xs text-gray uppercase tracking-wide pb-2 font-medium">Type</th>
                      <th className="text-right text-xs text-gray uppercase tracking-wide pb-2 font-medium">Price</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {booking.unit_days.map((day: BookingUnitDaySummary) => (
                      <tr key={day.id}>
                        <td className="py-2.5 font-medium text-primary">
                          {format(new Date(day.date), "MMM d, yyyy")}
                        </td>
                        <td className="py-2.5 text-gray">
                          {day.time_slot ? (
                            <span className="flex items-center gap-1.5">
                              {slotTypeIcon(day.time_slot.slot_type)}
                              <span className="capitalize">{day.time_slot.slot_type}</span>
                              <span className="text-xs">
                                {day.time_slot.starts_at} – {day.time_slot.ends_at}
                              </span>
                            </span>
                          ) : day.includes_overnight ? (
                            <span className="flex items-center gap-1.5">
                              <MoonIcon className="size-3.5" /> Overnight
                            </span>
                          ) : (
                            <span className="flex items-center gap-1.5">
                              <SunIcon className="size-3.5" /> Day only
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 text-right font-semibold text-primary">
                          {formatPrice(day.price, currency)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                  <tfoot>
                    <tr className="border-t-2 border-border">
                      <td colSpan={2} className="pt-3 text-sm font-semibold text-primary">
                        Unit days subtotal ({booking.unit_days.length} day{booking.unit_days.length !== 1 ? "s" : ""})
                      </td>
                      <td className="pt-3 text-right font-bold text-primary">
                        {formatPrice(unitDaysTotal, currency)}
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}
