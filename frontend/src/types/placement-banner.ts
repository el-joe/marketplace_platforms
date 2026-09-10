import { PaidAdMeta } from "@/src/components/shared/page-builder/types";

export interface PlacementBanner {
  id: string | null;
  title_en: string | null;
  title_ar: string | null;
  subtitle_en: string | null;
  subtitle_ar: string | null;
  cta_label_en: string | null;
  cta_label_ar: string | null;
  cta_url: string | null;
  link_type: string | null;
  link_reference_id: string | null;
  desktop_image_url: string | null;
  mobile_image_url: string | null;
  desktop_image_url_ar?: string | null;
  mobile_image_url_ar?: string | null;
  is_external?: boolean;
  is_paid?: boolean;
  ad?: PaidAdMeta | null;
}
