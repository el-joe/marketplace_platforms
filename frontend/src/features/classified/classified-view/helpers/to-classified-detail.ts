import { ClassifiedItem } from "../../classifiedList/helpers/types";
import { IClassified } from "./types";
import {
  ClassifiedDetail,
  ClassifiedSpecItem,
  RecommendedClassifiedItem,
} from "../types";

/**
 * Maps the real classified detail API response (IClassified, from
 * GET /listings/classified/{slug}) plus the "similar listings" response
 * into the ClassifiedDetail shape the classified-view components render.
 *
 * Known gaps (no backing data yet — see enhancement.md P-26 follow-up):
 * - favorites/wishlist for classifieds (isFavorite/favoritesCount default to
 *   false/0; there is no classified wishlist endpoint yet).
 * - rating/reviewsCount for a listing itself (classifieds have no review
 *   system, only a seller `positive_rating` percentage — default to 0).
 * - promotedBadge (no ad-boost flag is exposed on the detail resource yet).
 * - features checklist (interior/exterior/technology) — the backend stores
 *   only a flat `attributes` map, not a features taxonomy, so this section
 *   renders empty until that data model exists.
 * - seller phone/avatar — the detail API intentionally omits the seller's
 *   phone number (buyers contact sellers via the inquiry/chat flow instead)
 *   and there is no seller avatar field yet.
 */
export function toClassifiedDetail(
  listing: IClassified,
  related: ClassifiedItem[],
  locale: string,
): ClassifiedDetail {
  const isAr = locale === "ar";
  const attributeEntries = Object.entries(listing.attributes ?? {});

  const specItems: ClassifiedSpecItem[] = attributeEntries.map(
    ([label, value]) => ({
      label,
      value: String(value),
    }),
  );
  specItems.push(
    { label: "City", value: listing.location?.city?.[isAr ? "ar" : "en"] ?? "" },
    { label: "Category", value: listing.category?.name?.[isAr ? "ar" : "en"] ?? "" },
    { label: "Listing Id", value: listing.listing_number },
  );

  const mid = Math.ceil(specItems.length / 2);

  return {
    id: listing.listing_number,
    listingId: listing.listing_number,
    slug: listing.slug,
    titleAr: listing.title?.ar ?? "",
    titleEn: listing.title?.en ?? "",
    price: listing.price,
    currency: String(listing.currency),
    isFavorite: false,
    favoritesCount: 0,
    rating: 0,
    reviewsCount: 0,
    promotedBadge: undefined,
    quickSpecs: attributeEntries.slice(0, 4).map(([label, value]) => ({
      type: "tag" as const,
      label: `${label}: ${value}`,
    })),
    images: (listing.images ?? []).map((img, idx) => ({
      id: img.id,
      url: img.url,
      alt: listing.title?.[isAr ? "ar" : "en"] ?? "",
      isPrimary: idx === 0,
    })),
    specs: {
      column1: specItems.slice(0, mid),
      column2: specItems.slice(mid),
    },
    description: listing.description?.[isAr ? "ar" : "en"] ?? "",
    features: [],
    seller: {
      id: `${listing.slug}-seller`,
      name: listing.seller?.display_name ?? "",
      avatar: "",
      rating: listing.seller?.positive_rating ?? 0,
      reviewsCount: 0,
      memberSince: listing.seller?.member_since ?? "",
      totalListings: listing.seller?.active_listings ?? 0,
      phone: "",
      phoneMasked: "",
      isVerified: listing.seller?.type === "vendor",
    },
    recommended: related.map((item): RecommendedClassifiedItem => ({
      id: item.listing_id,
      title: isAr ? item.title_ar : item.title_en,
      specs: Object.values(item.attributes ?? {}).join(", "),
      location: item.location,
      image: item.thumbnail || item.images?.[0]?.url || "",
      slug: item.slug,
      isFavorite: false,
    })),
  };
}
