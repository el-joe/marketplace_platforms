import Image from "next/image";
import { Link } from "@/i18n/navigation";
import { getImageURL } from "@/src/helpers/get-image-url";
import { formatPrice } from "../helpers/format-price";
import type { MarketerListingEntry } from "../helpers/types";

interface Props {
  item: MarketerListingEntry;
  locale: "en" | "ar";
  labels: { exclusive: string; negotiable: string; classified: string };
}

/** Boutiqaat-style card: image, brand, name, old/new price, discount %, exclusive badge. */
export default function MarketerListingCard({ item, locale, labels }: Props) {
  const isAr = locale === "ar";

  if (item.kind === "classified") {
    const title = isAr ? item.title_ar : (item.title_en ?? item.title_ar);
    const category = item.category ? (isAr ? item.category.name_ar : (item.category.name_en ?? item.category.name_ar)) : null;
    return (
      <Link href={`/classified/find/${item.slug}`} className="group flex h-full flex-col">
        <div className="relative aspect-square overflow-hidden rounded-lg bg-gray-2">
          <Image
            src={getImageURL(item.first_image)}
            alt={title}
            fill
            sizes="(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 50vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
          <span className="absolute start-2 top-2 rounded bg-primary px-2 py-0.5 text-[10px] font-bold text-white">
            {labels.classified}
          </span>
        </div>
        <div className="mt-2 space-y-0.5">
          {category && <span className="block truncate text-xs text-gray">{category}</span>}
          <h3 className="line-clamp-2 text-sm font-semibold text-primary">{title}</h3>
          {item.price != null && (
            <p className="text-sm font-bold text-primary">
              {formatPrice(item.price, item.currency, locale)}
              {item.price_negotiable && <span className="ms-2 text-xs font-normal text-gray">{labels.negotiable}</span>}
            </p>
          )}
          {item.location && <p className="truncate text-xs text-gray">{item.location}</p>}
        </div>
      </Link>
    );
  }

  const name = isAr ? item.name_ar : item.name_en;
  const brand = item.brand ? (isAr ? item.brand.name.ar : item.brand.name.en) ?? item.brand.name.en ?? item.brand.name.ar : null;
  const hasDiscount = item.compare_at_price != null && item.compare_at_price > item.price;
  const percent =
    item.discount_percent ??
    (hasDiscount ? Math.round(((item.compare_at_price! - item.price) / item.compare_at_price!) * 100) : 0);

  return (
    <Link href={`/products/${item.url_param}`} className="group flex h-full flex-col">
      <div className="relative aspect-square overflow-hidden rounded-lg bg-gray-2">
        <Image
          src={getImageURL(item.primary_image)}
          alt={name}
          fill
          sizes="(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 50vw"
          className="object-cover transition-transform duration-300 group-hover:scale-105"
        />
        {item.is_exclusive && (
          <span className="absolute start-2 top-2 rounded bg-main px-2 py-0.5 text-[10px] font-bold text-white">
            {labels.exclusive}
          </span>
        )}
        {percent > 0 && (
          <span className="absolute end-2 top-2 rounded bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white" dir="ltr">
            -{percent}%
          </span>
        )}
      </div>
      <div className="mt-2 space-y-0.5">
        {brand && <span className="block truncate text-xs font-bold text-primary">{brand}</span>}
        <h3 className="line-clamp-2 text-sm text-primary">{name}</h3>
        <p className="flex flex-wrap items-baseline gap-x-2 text-sm font-bold text-primary">
          <span>{item.price_formatted || formatPrice(item.price, item.currency, locale)}</span>
          {hasDiscount && (
            <span className="text-xs font-normal text-gray line-through">
              {formatPrice(item.compare_at_price, item.currency, locale)}
            </span>
          )}
        </p>
      </div>
    </Link>
  );
}
