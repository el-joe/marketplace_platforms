"use client";

import Image from "next/image";
import { formatDistanceToNow } from "date-fns";
import { CalendarIcon, HeartIcon, MapPinIcon } from "lucide-react";

import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import Price from "@/src/components/shared/Price";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import useLocale from "@/src/hooks/use-locale";

import type { ClassifiedListing } from "../helpers/types";

type Props = {
  listing: ClassifiedListing;
};

const PURPOSE_LABEL: Record<
  ClassifiedListing["listing_purpose"],
  { en: string; ar: string; color: string }
> = {
  sale: { en: "For Sale", ar: "للبيع", color: "bg-green-100 text-green-700" },
  rent: { en: "For Rent", ar: "للإيجار", color: "bg-blue-100 text-blue-700" },
};

const ClassifiedCard = ({ listing }: Props) => {
  const locale = useLocale();
  const title = locale === "ar" ? listing.title_ar : listing.title_en;
  const purpose = PURPOSE_LABEL[listing.listing_purpose];
  const timeAgo = formatDistanceToNow(new Date(listing.created_at), {
    addSuffix: true,
  });

  return (
    <Link
      href={`/classified/listing/${listing.slug}`}
      className="group flex flex-col sm:flex-row gap-3 bg-white border border-border rounded-xl overflow-hidden hover:shadow-md transition-shadow"
    >
      <div className="relative w-full sm:w-44 h-44 sm:h-auto shrink-0 bg-gray-2">
        {listing.thumbnail ? (
          <Image
            src={listing.thumbnail}
            alt={title}
            fill
            className="object-cover group-hover:scale-105 transition-transform duration-300"
            sizes="(max-width: 640px) 100vw, 176px"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-gray-300">
            <span className="text-4xl">📷</span>
          </div>
        )}
        <span
          className={`absolute top-2 left-2 text-xs font-semibold px-2 py-0.5 rounded-full ${purpose?.color ?? "bg-gray-100 text-gray"}`}
        >
          {purpose?.[locale] ?? listing.listing_purpose}
        </span>
        <Button
          variant="ghost"
          className="absolute top-1 right-1 p-1! min-w-0! bg-white/80 rounded-full size-7"
          onClick={(e) => e.preventDefault()}
        >
          <HeartIcon className="size-4 text-gray" />
        </Button>
      </div>

      <div className="flex flex-col gap-1.5 p-3 flex-1 min-w-0">
        <h3 className="font-semibold text-primary line-clamp-2 text-sm leading-snug">
          {title}
        </h3>

        <div className="flex items-center gap-2 mt-1">
          <Price
            currentPrice={listing.price}
            currency={listing.currency as CurrencyCode}
            size="sm"
          />
          {listing.price_negotiable && (
            <span className="text-xs text-gray border border-border rounded px-1.5 py-0.5">
              {locale === "ar" ? "قابل للتفاوض" : "Negotiable"}
            </span>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-3 mt-auto text-xs text-gray">
          {listing.location && (
            <span className="flex items-center gap-1">
              <MapPinIcon className="size-3 shrink-0" />
              {listing.location}
            </span>
          )}
          <span className="flex items-center gap-1">
            <CalendarIcon className="size-3 shrink-0" />
            {timeAgo}
          </span>
          {listing.seller_type === "vendor" && (
            <span className="bg-blue-50 text-blue-600 px-1.5 py-0.5 rounded text-[10px] font-medium">
              {locale === "ar" ? "متجر" : "Store"}
            </span>
          )}
        </div>
      </div>
    </Link>
  );
};

export default ClassifiedCard;
