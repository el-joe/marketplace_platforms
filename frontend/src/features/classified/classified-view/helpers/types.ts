import { Currency } from "@/src/features/noon/cart/types/recommendations.type";

export interface IClassified {
  listing_number: string;
  slug: string;
  title: Description;
  description: Description;
  listing_purpose: string;
  price: number;
  currency: Currency;
  price_negotiable: boolean;
  attributes: unknown[];
  location: Location;
  category: Category;
  images: Image[];
  seller: Seller;
  views_count: number;
  expires_at: null;
  created_at: Date;
}

export interface Category {
  id: string;
  name: Description;
}

export interface Description {
  ar: string;
  en: string;
}

export interface Image {
  id: string;
  url: string;
  position: number;
}

export interface Location {
  city: Description;
}

export interface Seller {
  type: string;
  display_name: string;
  positive_rating: number;
  member_since: string;
  years_active: number;
  active_listings: number;
  store_url: null;
}
