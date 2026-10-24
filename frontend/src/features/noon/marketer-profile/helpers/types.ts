import type { PromoBadge } from "@/types/globals";
export interface MarketerProfileMarketer {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate" | string;
  gender?: "male" | "female" | null;
  country?: { name_en: string; name_ar: string } | null;
  total_campaigns: number;
  total_conversions: number;
}

export interface MarketerBrokerCategory {
  category_id: string;
  category_name_en: string | null;
  category_name_ar: string | null;
}

export interface MarketerBrokerSpecialization {
  categories: MarketerBrokerCategory[];
  city_id: string | null;
  city_name_en: string | null;
  city_name_ar: string | null;
  serves_all_cities: boolean;
}

export interface MarketerProfileInfo {
  slug: string;
  bio_ar: string | null;
  bio_en: string | null;
  video_url: string | null;
  social_links: Record<string, string>;
  contact_details: Record<string, string>;
  banner_url: string | null;
  avatar_url: string | null;
  qr_code_url: string | null;
  profile_url: string;
  ad_price?: number | null;
  ad_price_currency?: string | null;
  broker_specialization?: MarketerBrokerSpecialization | null;
  gender?: "male" | "female" | null;
}

export interface CampaignContext {
  id: string;
  title: string | null;
  vendor_name: string | null;
}

export interface MarketerProfileImage {
  id: string;
  url: string;
  alt: { en: string | null; ar: string | null };
  is_primary: boolean;
  position: number;
  variant_id: string | null;
}

export interface MarketerProfileListingItem {
  listing_id: string;
  listing_type: "marketer";
  product_id: string;
  product_slug: string;
  variant_id: string;
  variant_slug: string;
  variant_name: { en: string; ar: string };
  sku: string;
  name_en: string;
  name_ar: string;
  primary_image: string | null;
  images: MarketerProfileImage[];
  category_name: { en: string | null; ar: string | null };
  brand: { id: string; name: { en: string | null; ar: string | null }; logo_url: string | null } | null;
  price: number;
  price_formatted: string;
  compare_at_price: number | null;
  currency: string;
  condition: string;
  referral_code: string | null;
  referral_link: string | null;
  total_sold: number;
  rating_avg: number | null;
  rating_count: number;
  url_param: string;
  product_url: string;
  is_wishlisted: boolean;
  promo_badges?: PromoBadge[];
  campaign?: CampaignContext | null; // only on campaign_listings items
}

export interface ListingsMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface MarketerExclusiveContract {
  scope: "category" | "listing";
  category_name?: string | null;
  category_name_en?: string | null;
  listing_title?: string | null;
  listing_title_en?: string | null;
  ends_at?: string | null;
}

export interface MarketerClassifiedListing {
  id: string;
  listing_number: string;
  slug: string;
  title_ar: string;
  title_en?: string | null;
  price: number;
  currency: string;
  price_negotiable: boolean;
  category: { name_ar: string | null; name_en?: string | null };
  first_image?: string | null;
  listing_purpose: "sale" | "rent" | string;
  views_count: number;
}

export interface MarketerProfileData {
  exclusive_contracts?: MarketerExclusiveContract[];
  classified_listings?: MarketerClassifiedListing[];
  classified_count?: number;
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  own_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
  /** Vendor campaign products (campaign.vendor_id NOT NULL) */
  vendor_campaign_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
  /** Cross-marketer products (campaign.vendor_id NULL, owner = another marketer) */
  marketer_campaign_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
  /** @deprecated Use vendor_campaign_listings — kept for backward compat */
  campaign_listings?: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
}

/* ------------------------------------------------------------------ */
/* Boutiqaat-style details page contract (design doc §3)               */
/* ------------------------------------------------------------------ */

export interface MarketerBanner {
  id: string;
  title: string | null;
  subtitle: string | null;
  cta_label: string | null;
  link: string | null;
  image_desktop: string | null;
  image_mobile: string | null;
  priority?: number;
  click_url: string | null;
}

export type ListingSource = "all" | "own" | "for_sellers";
export type ListingKind = "all" | "products" | "classified";
export type ListingSort = "newest" | "price_asc" | "price_desc";

export interface FacetOption {
  id: string;
  name: string;
  count: number;
}

export interface MarketerFacets {
  sources?: { key: string; count: number }[];
  kinds?: { key: string; count: number }[];
  categories?: FacetOption[];
  brands?: FacetOption[];
  price?: { min: number; max: number } | null;
  discounted_count?: number;
}

export type MarketerProductCard = MarketerProfileListingItem & {
  kind?: "product";
  is_exclusive?: boolean;
  discount_percent?: number | null;
};

export type MarketerClassifiedCard = {
  kind: "classified";
  id: string;
  slug: string;
  title_ar: string;
  title_en?: string | null;
  price: number | null;
  currency: string;
  price_negotiable?: boolean;
  first_image?: string | null;
  location?: string | null;
  category?: { name_ar: string | null; name_en?: string | null } | null;
};

export type MarketerListingEntry = MarketerProductCard | MarketerClassifiedCard;

export interface MarketerListings {
  items: MarketerListingEntry[];
  meta: ListingsMeta;
}

export interface MarketerFilters {
  source: ListingSource;
  kind: ListingKind;
  q: string;
  category: string[];
  brand: string[];
  priceMin: string;
  priceMax: string;
  discount: boolean;
  sort: ListingSort;
}

/** Normalised data the new page works with (profile + contract extras). */
export type MarketerProfilePageData = Omit<MarketerProfileData, "own_listings"> & {
  own_listings?: MarketerProfileData["own_listings"];
  banners: MarketerBanner[];
  listings: MarketerListings;
  facets: MarketerFacets | null;
};
