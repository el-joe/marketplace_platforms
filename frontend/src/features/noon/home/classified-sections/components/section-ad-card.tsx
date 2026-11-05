import Image from "next/image";
import { MapPin } from "lucide-react";
import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { ClassifiedItem } from "@/src/features/classified/classifiedList/helpers/types";

type Props = {
  listing: ClassifiedItem;
  locale: "en" | "ar";
};

export default function SectionAdCard({ listing, locale }: Props) {
  const title = locale === "ar" ? listing.title_ar : listing.title_en;
  const image = listing.images[0]?.url ?? listing.thumbnail;

  return (
    <Link
      href={`/classified/find/${listing.slug}`}
      className="block overflow-hidden rounded-xl border border-gray-200 bg-white transition-shadow hover:shadow-md"
    >
      <div className="relative aspect-4/3 bg-gray-100">
        {image && (
          <Image
            src={image}
            alt={title}
            fill
            sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
            className="object-cover"
          />
        )}
      </div>
      <div className="p-3">
        <Price currentPrice={listing.price} currency={listing.currency} />
        <h3 className="mt-1 line-clamp-2 text-sm font-semibold text-gray-900">
          {title}
        </h3>
        {listing.location && (
          <p className="mt-1 flex items-center gap-1 text-xs text-gray-500">
            <MapPin className="size-3 shrink-0" />
            <span className="truncate">{listing.location}</span>
          </p>
        )}
      </div>
    </Link>
  );
}
