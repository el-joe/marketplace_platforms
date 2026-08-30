import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { Name, ShippingBadge } from "@/types/globals";

export interface IHome {
  nav: Nav[];
  page_builder: PageBuilder;
  has_page_builder: boolean;
  sections: WelcomeSection[];
  meta: Meta;
}

export interface Meta {
  country_code: string;
  currency: CurrencyCode;
  locale: string;
}

export interface Nav {
  type: Type;
  id: string;
  name: string;
  slug: string;
  icon: null | string;
  children: Nav[];
}

export enum Type {
  Product = "product",
  Travel = "travel",
}

export interface Vendor {
  id: string;
  store_name: StoreName;
  rating: number;
}

export enum StoreName {
  TechZoneElectronics = "TechZone Electronics",
}



export interface WelcomeSection {
  section_type: string;
  items: SectionItem[];
  title?: string;
}

export interface SectionItem {
  listing_id: string;
  listing_type: string;
  listing_ref: string;
  sku: string;
  vendor_sku: null | string;
  admin_sku?: string;
  product_id: string;
  product_slug: string;
  slug: string;
  variant_id: string;
  variant_slug: string;
  variant_name: string;
  variant_image: string;
  primary_image: string;
  product_url: string;
  url_param: string;
  name?: Name;
  name_ar: string;
  name_en: string;
  price: number;
  price_formatted: string;
  currency: CurrencyCode;
  condition: string;
  is_admin_listing: boolean;
  is_express_fbn: boolean;
  fulfillment_model: string;
  express_badge?: ExpressBadge;
  sold_by?: Name;
  vendor: Vendor | null;
  rating_avg: number | null;
  rating_count: number;
  is_wishlisted: boolean;
  is_sponsored: boolean;
  shipping_badge: ShippingBadge | null;
  thumbnail?: string;
  total_sold?: number;
}

export interface ExpressBadge {
  label: Name;
}
