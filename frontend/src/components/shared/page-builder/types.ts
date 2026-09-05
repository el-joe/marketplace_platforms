import { Category, Name, Product, SEO } from "@/types/globals";

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
  // card_style?: "normal" | "special";
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
  card_style?: "normal" | "special";
  content_html_en?: null | string;
  content_html_ar?: null | string;
  text_align?: "left" | "center" | "right" | "justify";
  max_width?: null | string;
}

export interface Slide {
  id: string;
  position: number;
  desktop_url: Name;
  mobile_url: Name;
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
  image_url: Name;
  mobile_image_url: Name;
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
  image_url_en: string;
  image_url_ar: null | string;
  badge_label_ar: null | string;
  badge_label_en: null | string;
}

export interface ColumnTile {
  label: Name;
  badge: Name;
  image_url: Name;
  link_url: string;
  is_paid?: boolean;
}

export interface BlockItem {
  image_url: Name;
  link_url: string;
  title: Name;
  subtitle: Name;
  badge: Name;
  id: string;
  position: number;
  url: Name;
  link_open_new_tab: boolean;
  alt_text: Name;
  show_title_overlay: boolean;
  aspect_ratio: string;
  is_paid: boolean;
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
  background_image_url: Name;
  background_image_type?: "section" | "header";
  max_width: null | string;
  padding_top: number;
  padding_bottom: number;
  columns: Array<Block[]>;
  blocks: Block[];
}

export interface ColumnsConfig {
  columns: number;
  widths: string;
}
