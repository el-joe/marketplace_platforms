import { Currency } from "@/src/features/noon/cart/types/recommendations.type";

export interface IClassifiedsList {
  /** The resolved category of a category page; null on the all-ads landing page. */
  category?: IClassifiedBrowseCategory | null;
  listings: Listings;
}

/** `category` block of GET /browse/classified/{idOrSlug}. */
export interface IClassifiedBrowseCategory {
  id: string;
  slug: string;
  name: Name;
  icon: null | string;
  image_url: null | string;
  description: { ar: null | string; en: null | string };
  parent: { id: string; slug: string; name: Name } | null;
  children: { id: string; slug: string; name: Name }[];
}

/** Minimal shape the breadcrumb / filter chips need. */
export interface IClassifiedCategoryRef {
  id: string;
  slug: string;
  name: Name;
}

/** One admin-configured section with its newest ads: GET /classified/sections. */
export interface IClassifiedSection {
  id: string;
  slug: string;
  name: Name;
  icon: null | string;
  image_url: null | string;
  description: { ar: null | string; en: null | string };
  total: number;
  children: { id: string; slug: string; name: Name }[];
  listings: ClassifiedItem[];
}

export interface Listings {
  items: ClassifiedItem[];
  meta: Meta;
}

export interface ClassifiedItem {
  listing_id: string;
  listing_number: string;
  source_type: string;
  title_en: string;
  title_ar: string;
  slug: string;
  thumbnail: string;
  images: Image[];
  price: number;
  price_formatted: string;
  currency: Currency;
  price_negotiable: boolean;
  listing_purpose: string;
  location: string;
  seller_type: string;
  images_count: number;
  attributes: Record<string, string>;
  created_at: Date;
  exclusive_contract?: {
    marketer: {
      id: string;
      name: string;
    };
    expires_at: string | null;
  } | null;
}

export interface Image {
  id: string;
  url: string;
  is_primary: boolean;
  position: number;
}

export interface Meta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// classified categories

export interface IClassifiedCategoriesList {
  id: string;
  type: DatumType;
  name: Name;
  slug: string;
  parent_id: null | string;
  image_url?: null | string;
  product_count?: number;
  brands?: Brand[];
  attributes?: Attribute[];
  children: IClassifiedCategoriesList[];
  icon?: null | string;
  /** Surfaces the admin put the section on: nav, landing, home. */
  placements?: string[];
  description?: { ar: null | string; en: null | string };
}

export interface Attribute {
  id: string;
  code: string;
  name: Name;
  type: AttributeType;
  unit: null | string;
  is_required: boolean;
  values: Value[];
}

export interface Name {
  ar: string;
  en: string;
}

export enum AttributeType {
  Color = "color",
  Number = "number",
  Select = "select",
}

export interface Value {
  id: string;
  value: Name;
  color_hex: null;
  swatch_image_url: null;
}

export interface Brand {
  id: string;
  name: Name;
  slug: string;
  logo_url: string;
}

export enum DatumType {
  Classified = "classified",
  Product = "product",
  Travel = "travel",
}
