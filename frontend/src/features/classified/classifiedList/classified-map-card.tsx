"use client";

import Image from "next/image";
import { MapPin as MapPinIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { cn } from "@/src/lib/utils";
import { MapPin } from "./api/get";

type Props = {
  pin: MapPin;
  active: boolean;
  onHover: (id: string | null) => void;
  className?: string;
};

/** Compact ad card for the map split view; its data is the same pin the map draws. */
export default function ClassifiedMapCard({
  pin,
  active,
  onHover,
  className,
}: Props) {
  const t = useTranslations("classified");

  return (
    <Link
      id={`pin-card-${pin.id}`}
      href={`/classified/find/${pin.slug}`}
      onMouseEnter={() => onHover(pin.id)}
      onMouseLeave={() => onHover(null)}
      className={cn(
        "flex gap-3 rounded-xl border bg-white p-2.5 transition-shadow hover:shadow-md",
        active ? "border-blue-600 ring-2 ring-blue-600/30" : "border-gray-200",
        className,
      )}
    >
      <div className="relative size-24 shrink-0 overflow-hidden rounded-lg bg-gray-100 sm:size-28">
        <Image
          src={pin.thumbnail || "/images/no-image-available-icon.jpg"}
          alt={pin.title}
          fill
          sizes="112px"
          className="object-cover"
        />
      </div>
      <div className="flex min-w-0 flex-1 flex-col justify-between py-0.5">
        <div className="min-w-0">
          <Price
            currentPrice={pin.price}
            currency={pin.currency as CurrencyCode}
            size="sm"
          />
          <h3 className="line-clamp-2 text-sm font-bold leading-snug text-gray-900">
            {pin.title}
          </h3>
        </div>
        {pin.purpose && (
          <p className="flex items-center gap-1 text-xs text-gray-500">
            <MapPinIcon className="size-3 shrink-0 text-gray-400" />
            {t("for", { type: pin.purpose })}
          </p>
        )}
      </div>
    </Link>
  );
}
