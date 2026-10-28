export interface IMarketersListResponse {
  items: IMarketerItem[];
  meta: Meta;
}

export interface IMarketerItem {
  id: string;
  slug: string;
  name: string;
  marketer_type: string;
  type: string;
  type_label: string;
  gender: "male" | "female";
  profile_slug: string;
  profile_url: string;
  banner_url?: null;
  avatar_url?: string | null;
  total_campaigns: number;
  total_conversions: number;
  avatar_initial: string;
  specialty?: null;
  specialty_ar?: null | string;
  specialty_en?: null | string;
  ad_price: number;
  ad_price_currency: string;
  classified_count: number;
}

export interface Meta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface IMarketerBanner {
  id: string;
  title: string | null;
  subtitle: string | null;
  cta_label: string | null;
  link: string | null;
  link_type: string | null;
  link_reference_id?: string | null;
  image_desktop: string | null;
  image_mobile: string | null;
  priority: number;
  click_url: string | null;
}

export interface IMarketersBannersResponse {
  banners: {
    hero: IMarketerBanner[];
    strip: IMarketerBanner[];
  };
}
