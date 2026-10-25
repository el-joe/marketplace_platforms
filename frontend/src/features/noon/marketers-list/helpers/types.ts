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
  avatar_url?: null;
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
