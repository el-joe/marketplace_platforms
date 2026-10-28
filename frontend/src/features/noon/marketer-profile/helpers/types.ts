import { IProduct } from "@/types";

export interface IMarketer {
  marketer: Marketer;
  profile: Profile;
  own_listings: OwnListings;
  vendor_campaign_listings: VendorCampaignListings;
  marketer_campaign_listings: MarketerCampaignListings;
  campaign_listings: CampaignListings;
  exclusive_contracts: unknown[];
  classified_listings: unknown[];
  classified_count: number;
  listings: Listings;
  facets: Facets;
  banners: Banners[];
}

export interface Marketer {
  id: string;
  name: string;
  marketer_type: string;
  country: Country;
  total_campaigns: number;
  total_conversions: number;
}

export interface Country {
  name_en: string;
  name_ar: string;
}

export interface Profile {
  slug: string;
  gender: string;
  bio_ar: string;
  bio_en: string;
  specialty_ar?: string | null;
  specialty_en?: string | null;
  video_url?: null;
  social_links: SocialLinks;
  contact_details: ContactDetails;
  banner_url?: string | null;
  avatar_url?: string | null;
  qr_code_url: string;
  profile_url: string;
  ad_price: number;
  ad_price_currency: string;
  measurements: Measurements;
  broker_specialization?: null;
}

export interface SocialLinks {
  instagram: string;
}

export interface ContactDetails {
  niche: string;
  engagement_rate: number;
  followers_count: number;
}

export interface Measurements {
  clothing_size?: null;
  shirt_size?: null;
  pants_size?: null;
  dress_size?: null;
  abaya_size?: null;
  shoe_size?: null;
  shoe_size_system?: null;
  chest_cm?: null;
  waist_cm?: null;
  hip_cm?: null;
  height_cm?: null;
  item_length_cm?: null;
  sleeve_from_neck_cm?: null;
  sleeve_from_shoulder_cm?: null;
  sleeve_width_cm?: null;
  notes?: null;
}

export interface OwnListings {
  items: IProduct[];
  meta: Meta;
}

export interface Meta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface VendorCampaignListings {
  items: IProduct[];
  meta: Meta;
}

export interface VariantName {
  ar: string;
  en: string;
}

export interface Image {
  url: string;
  alt: Alt;
}

export interface Alt {
  ar?: null;
  en?: null;
}

export interface Images {
  id: string;
  url: string;
  alt: Alt;
  is_primary: boolean;
  position: number;
}

export interface Name {
  ar: string;
  en: string;
}

export interface ProductName {
  ar: string;
  en: string;
}

export interface CategoryName {
  en: string;
  ar: string;
}

export interface Brand {
  id: string;
  name: Name;
  slug: string;
  logo_url: string;
}

export interface Campaign {
  id: string;
  title?: string;
  vendor_name: string;
}

export interface MarketerCampaignListings {
  items: IProduct[];
  meta: Meta;
}

export interface CampaignListings {
  items: IProduct[];
  meta: Meta;
}

export interface Listings {
  items: IProduct[];
  meta: Meta;
}

export interface Facets {
  sources: Sources[];
  kinds: Kinds[];
  categories: Categories[];
  brands: Brands[];
  price: Price;
  discounted_count: number;
}

export interface Sources {
  key: string;
  count: number;
}

export interface Kinds {
  key: string;
  count: number;
}

export interface Categories {
  id: string;
  name: string;
  count: number;
}

export interface Brands {
  id: string;
  name: string;
  count: number;
}

export interface Price {
  min: number;
  max: number;
}

export interface Banners {
  id: string;
  title: string;
  subtitle: string;
  cta_label: string;
  link: string;
  link_type: string;
  link_reference_id?: null;
  image_desktop: string;
  image_mobile: string;
  priority: number;
  click_url: string;
}
