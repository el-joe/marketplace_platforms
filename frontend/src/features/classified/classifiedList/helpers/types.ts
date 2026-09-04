import { Currency } from "@/src/features/noon/cart/types/recommendations.type";

export interface IClassifiedsList {
  category: null;
  page_builder: null;
  listings: Listings;
}

export interface Listings {
  items: Item[];
  meta: Meta;
}

export interface Item {
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
  created_at: Date;
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
