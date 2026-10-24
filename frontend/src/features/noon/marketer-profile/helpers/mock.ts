/**
 * Dev-only mock for the marketer details contract (design doc §3).
 * Enabled with NEXT_PUBLIC_MARKETER_PROFILE_MOCK=1 (ignored in production).
 * It only fills in what the backend response does not yet contain
 * (`banners`, `listings`, `facets`), so the real API wins as soon as it lands.
 */
import type {
  ListingsMeta,
  MarketerBanner,
  MarketerClassifiedCard,
  MarketerFacets,
  MarketerFilters,
  MarketerListingEntry,
  MarketerListings,
  MarketerProductCard,
  MarketerProfileData,
} from "./types";

export const isMockEnabled = (): boolean =>
  process.env.NODE_ENV !== "production" && process.env.NEXT_PUBLIC_MARKETER_PROFILE_MOCK === "1";

const svg = (label: string, a: string, b: string, w = 1000, h = 280) =>
  `data:image/svg+xml;utf8,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><defs><linearGradient id="g"><stop offset="0" stop-color="${a}"/><stop offset="1" stop-color="${b}"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><text x="50%" y="50%" fill="#fff" font-size="40" font-family="sans-serif" text-anchor="middle">${label}</text></svg>`,
  )}`;

export const MOCK_BANNERS: MarketerBanner[] = [
  { id: "mock-1", title: "Mock banner 1", subtitle: null, cta_label: null, link: "/", image_desktop: svg("Banner 1", "#b8860b", "#3a2a00"), image_mobile: svg("Banner 1", "#b8860b", "#3a2a00", 600, 300), click_url: null },
  { id: "mock-2", title: "Mock banner 2", subtitle: null, cta_label: null, link: "/", image_desktop: svg("Banner 2", "#1f2937", "#6b7280"), image_mobile: svg("Banner 2", "#1f2937", "#6b7280", 600, 300), click_url: null },
];

const MOCK_CLASSIFIEDS: MarketerClassifiedCard[] = [1, 2, 3].map((i) => ({
  kind: "classified" as const,
  id: `mock-cl-${i}`,
  slug: `mock-classified-${i}`,
  title_ar: `إعلان مبوب تجريبي ${i}`,
  title_en: `Mock classified ad ${i}`,
  price: 150000 * i,
  currency: "EGP",
  price_negotiable: i % 2 === 0,
  first_image: svg(`Ad ${i}`, "#0f766e", "#134e4a", 600, 450),
  location: "Cairo",
  category: { name_ar: "عقارات", name_en: "Real estate" },
}));

const t = (e: MarketerListingEntry) =>
  e.kind === "classified" ? `${e.title_en ?? ""} ${e.title_ar}` : `${e.name_en} ${e.name_ar}`;

export function buildMockListings(
  data: MarketerProfileData,
  f: MarketerFilters,
  page: number,
  perPage: number,
): { listings: MarketerListings; facets: MarketerFacets } {
  const own: MarketerProductCard[] = (data.own_listings?.items ?? []).map((i, n) => ({ ...i, kind: "product", is_exclusive: n % 3 === 0 }));
  const promo: MarketerProductCard[] = [
    ...(data.vendor_campaign_listings?.items ?? []),
    ...(data.marketer_campaign_listings?.items ?? []),
  ].map((i) => ({ ...i, kind: "product" as const }));
  const ownIds = new Set(own.map((i) => i.listing_id));
  const sellers: MarketerListingEntry[] = [...promo.filter((p) => !ownIds.has(p.listing_id)), ...MOCK_CLASSIFIEDS];

  let items: MarketerListingEntry[] =
    f.source === "own" ? own : f.source === "for_sellers" ? sellers : [...own, ...sellers];
  if (f.source !== "own" && f.kind !== "all") {
    items = items.filter((i) => (f.kind === "classified") === (i.kind === "classified"));
  }
  const all = [...own, ...sellers];
  const facets: MarketerFacets = {
    sources: [
      { key: "all", count: all.length },
      { key: "own", count: own.length },
      { key: "for_sellers", count: sellers.length },
    ],
    kinds: [
      { key: "products", count: all.filter((i) => i.kind !== "classified").length },
      { key: "classified", count: MOCK_CLASSIFIEDS.length },
    ],
    categories: [{ id: "c1", name: "Fashion", count: 5 }, { id: "c2", name: "Beauty", count: 3 }],
    brands: [{ id: "b1", name: "Brand A", count: 4 }, { id: "b2", name: "Brand B", count: 2 }],
    price: { min: 0, max: 5000 },
    discounted_count: all.filter((i) => i.kind !== "classified" && (i.compare_at_price ?? 0) > i.price).length,
  };

  if (f.q) items = items.filter((i) => t(i).toLowerCase().includes(f.q.toLowerCase()));
  if (f.discount) items = items.filter((i) => i.kind !== "classified" && (i.compare_at_price ?? 0) > i.price);
  const min = parseFloat(f.priceMin), max = parseFloat(f.priceMax);
  if (!Number.isNaN(min)) items = items.filter((i) => (i.price ?? 0) >= min);
  if (!Number.isNaN(max)) items = items.filter((i) => (i.price ?? 0) <= max);
  if (f.sort === "price_asc") items = [...items].sort((a, b) => (a.price ?? 0) - (b.price ?? 0));
  if (f.sort === "price_desc") items = [...items].sort((a, b) => (b.price ?? 0) - (a.price ?? 0));

  const total = items.length;
  const meta: ListingsMeta = { current_page: page, last_page: Math.max(1, Math.ceil(total / perPage)), per_page: perPage, total };
  return { listings: { items: items.slice((page - 1) * perPage, page * perPage), meta }, facets };
}
