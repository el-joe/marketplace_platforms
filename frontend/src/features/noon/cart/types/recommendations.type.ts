import { IProduct } from "@/types";

export interface IRecommendations {
  sections: Section[];
  meta: Meta;
}

export interface Meta {
  total_sections: number;
  listing_mode: string;
}

export interface Section {
  section_type: string;
  title_en: string;
  title_ar: string;
  listings: IProduct[];
}

export interface Listing {
  id: string;
  listing_type: ListingType;
  price: number;
  compare_at_price: null;
  currency: Currency;
  condition: Condition;
  rating_avg: null;
  rating_count: number;
  vendor_covers_delivery: boolean;
  vendor: Vendor;
  product_variant: Product;
  product: Product;
  primary_image_url: string;
}

export enum Condition {
  New = "new",
}

export enum Currency {
  Aed = "AED",
}

export enum ListingType {
  VendorListing = "vendor_listing",
}

export interface Product {
  id: string;
  name_en: null | string;
  name_ar: null | string;
  slug?: string;
  sku?: string;
}

export interface Vendor {
  id: string;
  name: Name;
}

export enum Name {
  KhalidAlMansouri = "Khalid Al-Mansouri",
}
