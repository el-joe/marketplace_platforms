import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

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

export interface PageBuilder {
  page_id: string;
  page_type: string;
  version: number;
  seo: SEO;
  sections: PageBuilderSection[];
  blocks: unknown[];
  has_sections: boolean;
  total_block_count: number;
}

export interface PageBuilderSection {
  id: string;
  name: string;
  position: number;
  layout: "stack" | "columns";
  columns_config: ColumnsConfig | null;
  background_color: null | string;
  background_image_url: null | string;
  background_image_type?: "section" | "header";
  max_width: null | string;
  padding_top: number;
  padding_bottom: number;
  columns: Array<Block[]>;
  blocks: Block[];
}

export interface Block {
  id: string;
  block_type: string;
  position: number;
  device_target: string;
  config: BlockConfig;
  background_color: null | string;
  items?: BlockItem[];
  title?: Name;
  columns?: number;
  rows?: number;
  scrollable?: boolean;
  show_label?: boolean;
  show_badge?: boolean;
  image_shape?: string;
  size_preset?: string;
  aspect_ratio?: string;
  products?: Product[];
  slides?: Slide[];
  banner?: Banner;
  categories?: Category[];
  show_countdown?: boolean;
  seconds_remaining?: number;
  ends_at?: Date;
  show_view_all?: boolean;
  tiles?: ColumnTile[];
  rows_count?: number;
  card_style?: "normal" | "special";
}

export interface Category {
  id: string;
  name: Name;
  slug: string;
}

export interface Name {
  ar: null | "ar" | "en";
  en: null | "ar" | "en";
}

export interface BlockConfig {
  rows?: string;
  columns?: string;
  title_ar?: null | string;
  title_en?: null | string;
  scrollable?: number;
  show_badge?: number;
  show_label?: number;
  image_shape?: string;
  size_preset?: string;
  aspect_ratio?: null | string;
  source?: string;
  category_id?: null | string;
  max_products?: string;
  show_ratings?: number;
  flash_sale_id?: null | string;
  items_per_row?: string;
  show_view_all?: number;
  scrollable_row?: number;
  show_discount_badge?: number;
  loop?: number;
  show_dots?: number;
  show_arrows?: number;
  height_desktop?: string;
  autoplay_seconds?: string;
  max_items?: string;
  show_product_count?: number;
  banner_id?: string;
  mobile_aspect_ratio?: string;
  ends_at?: string;
  show_countdown?: number;
  tiles?: ConfigTile[];
  grid_cols?: string;
  grid_rows?: string;
  is_announcement?: boolean;
  rows_count?: string;
  card_style?: string;
}

export interface BlockItem {
  image_url: string;
  link_url: string;
  title: Name;
  subtitle: Name;
  badge: Name;
  id: string;
  position: number;
  url: string;
  link_open_new_tab: boolean;
  alt_text: Name;
  show_title_overlay: boolean;
  aspect_ratio: string;
  is_paid: boolean;
}

export interface Product {
  listing_id: string;
  listing_type: string;
  listing_ref: string;
  sku: string;
  vendor_sku: null | string;
  product_id: string;
  product_slug: string;
  slug: string;
  variant_id: string;
  variant_slug: string;
  product_url: string;
  url_param: string;
  variant_name: string;
  variant_image: string;
  primary_image: string;
  name_en: string;
  name_ar: string;
  thumbnail: string;
  category_name?: { en: string | null; ar: string | null };
  price: number;
  price_formatted: string;
  compare_at_price?: number | null;
  currency: CurrencyCode;
  condition: string;
  is_admin_listing: boolean;
  is_express_fbn: boolean;
  fulfillment_model: string;
  vendor: Vendor;
  shipping_badge: ShippingBadge | null;
  rating_avg: null | number;
  rating_count: number;
  total_sold: number;
  is_wishlisted: boolean;
  is_sponsored: boolean;
}

export interface ShippingBadge {
  label_en?: string;
  label_ar?: string;
  color_hex: string;
  text_color_hex: string;
  delivery_days_min: number;
  delivery_days_max: number;
  label?: Name;
}

export interface Vendor {
  id: string;
  store_name: StoreName;
  rating: number;
}

export enum StoreName {
  TechZoneElectronics = "TechZone Electronics",
}

export interface Slide {
  id: string;
  position: number;
  desktop_url: string;
  mobile_url: string;
  title: Name;
  subtitle: Name;
  cta_label: Name;
  cta_url: string;
  cta_open_new_tab: boolean;
  text_color: string;
  text_position: string;
  overlay_opacity: number;
  link_type: null | string;
  link_reference_id: null | string;
  is_paid?: boolean;
}

export interface Banner {
  image_url: string;
  mobile_image_url: string;
  link_url: string;
  link_type: string;
  link_reference_id: null | string;
  alt_text: Name;
  aspect_ratio: string;
  mobile_aspect_ratio: string;
}

export interface ConfigTile {
  label_ar: null | string;
  label_en: null | string;
  link_url: string;
  image_url: string;
  badge_label_ar: null | string;
  badge_label_en: null | string;
}

export interface ColumnTile {
  label: Name;
  badge: Name;
  image_url: string;
  link_url: string;
  is_paid?: boolean;
}

export interface ColumnsConfig {
  columns: number;
  widths: string;
}

export interface SEO {
  title: null | string;
  description: null | string;
  og_image_url: null | string;
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
