export type MarketerGender = "male" | "female";
export type MarketerType = "influencer" | "affiliate" | "broker";
/** URL-facing gender value (`?gender=`). */
export type GenderParam = "men" | "women";

export interface MarketerCardData {
  id: string;
  slug: string;
  name: string;
  avatar_url: string | null;
  banner_url: string | null;
  type: MarketerType | string;
  type_label: string | null;
  gender: MarketerGender | null;
  specialty: string | null;
}

export interface MarketerBanner {
  id: string;
  title: string | null;
  subtitle: string | null;
  cta_label: string | null;
  link: string | null;
  image_desktop: string | null;
  image_mobile: string | null;
  priority: number;
  click_url: string | null;
}

export interface MarketerSection {
  type: MarketerType | string;
  label: string | null;
  total: number;
  items: MarketerCardData[];
}

export interface MarketerBanners {
  hero: MarketerBanner[];
  strip: MarketerBanner[];
}

export interface GroupedMarketers {
  sections: MarketerSection[];
  banners: MarketerBanners;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface MarketersPage {
  items: MarketerCardData[];
  meta: PaginationMeta;
}

export interface ListSearchParams {
  gender: GenderParam;
  type?: MarketerType;
  page: number;
}
