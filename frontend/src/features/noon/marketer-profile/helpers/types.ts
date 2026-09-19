import type { PromoBadge } from "@/types/globals";
export interface MarketerProfileMarketer {
  id: string;
  name: string;
  marketer_type: "influencer" | "affiliate";
  country?: { name_en: string; name_ar: string } | null;
  total_campaigns: number;
  total_conversions: number;
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

export interface MarketerProfileData {
  marketer: MarketerProfileMarketer;
  profile: MarketerProfileInfo;
  own_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
  campaign_listings: { items: MarketerProfileListingItem[]; meta: ListingsMeta };
}
