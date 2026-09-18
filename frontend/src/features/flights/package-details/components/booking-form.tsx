"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { MinusIcon, PlusIcon, AlertCircleIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import Price from "@/src/components/shared/Price";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { useBookingActions } from "../helpers/use-booking-actions";
import { priceForTravelersCount } from "../helpers/price-for-travelers-count";

const MAX_TRAVELERS = 50;

type Props = {
  slug: string;
  price: number;
  currency: CurrencyCode;
  seatsRemaining: number | null;
  priceTiers: { travelers_count: number; price: number }[] | null;
};

export default function BookingForm({
  slug,
  price,
  currency,
  seatsRemaining,
  priceTiers,
}: Props) {
  const t = useTranslations("flights.packageDetails");
  const { book, isBooking, error } = useBookingActions(slug);

  const maxTravelers = Math.min(
    MAX_TRAVELERS,
    seatsRemaining !== null ? Math.max(seatsRemaining, 0) : MAX_TRAVELERS,
  );
  const soldOut = seatsRemaining !== null && seatsRemaining <= 0;

  const [travelersCount, setTravelersCount] = useState(soldOut ? 0 : 1);

  const totalPrice = priceForTravelersCount(
    travelersCount,
    price,
    priceTiers,
  );

  function decrement() {
    setTravelersCount((count) => Math.max(1, count - 1));
  }

  function increment() {
    setTravelersCount((count) => Math.min(maxTravelers, count + 1));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (soldOut || travelersCount < 1) return;
    await book(travelersCount);
  }

  return (
    <form className="flex flex-col gap-4" onSubmit={handleSubmit}>
      <div>
        <label className="text-xs text-gray uppercase tracking-widest block mb-2">
          {t("travelersCountLabel")}
        </label>
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={decrement}
            disabled={soldOut || travelersCount <= 1 || isBooking}
            className="size-9 shrink-0 rounded-full border border-border flex items-center justify-center text-primary disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-2/40 transition-colors"
            aria-label={t("decreaseTravelers")}
          >
            <MinusIcon className="size-4" />
          </button>
          <span className="w-10 text-center font-bold text-primary">
            {travelersCount}
          </span>
          <button
            type="button"
            onClick={increment}
            disabled={soldOut || travelersCount >= maxTravelers || isBooking}
            className="size-9 shrink-0 rounded-full border border-border flex items-center justify-center text-primary disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-2/40 transition-colors"
            aria-label={t("increaseTravelers")}
          >
            <PlusIcon className="size-4" />
          </button>
        </div>
      </div>

      <div className="flex items-center justify-between border-t border-border pt-4">
        <span className="text-sm text-gray">{t("totalPrice")}</span>
        <Price currentPrice={totalPrice} currency={currency} size="lg" />
      </div>

      {error && (
        <div className="flex items-start gap-2 text-sm text-red bg-red/5 rounded-lg px-3 py-2">
          <AlertCircleIcon className="size-4 shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}

      <Button
        type="submit"
        disabled={soldOut || isBooking}
        className="bg-blue-3 hover:opacity-90 active:scale-[0.98] text-white border-transparent font-bold w-full"
      >
        {soldOut ? t("soldOut") : isBooking ? t("bookingInProgress") : t("bookNow")}
      </Button>
    </form>
  );
}
