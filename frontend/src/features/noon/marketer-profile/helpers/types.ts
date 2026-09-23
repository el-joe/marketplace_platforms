import type { PromoBadge } from "@/types/globals";
export interface MarketerProfileMarketer {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate" | string;
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
  campaign_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
}
