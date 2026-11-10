import { fetchGlobalInstance } from "../lib/utils";

export interface FooterCategoryLink {
  id: string;
  name: { ar: string | null; en: string | null };
  slug: string;
  link: string;
}

export interface FooterCategory extends FooterCategoryLink {
  children: FooterCategoryLink[];
}

export interface FooterLink {
  id: string;
  platform: string | null;
  label: { ar: string | null; en: string | null };
  url: string | null;
  icon: string | null;
}

/** An admin-managed static page linked from the footer (Admin > Static Pages). */
export interface FooterStaticPageLink {
  id: string;
  slug: string;
  title: { ar: string | null; en: string | null };
  footer_group: FooterStaticPageGroup | null;
  sort_order: number;
  /** Storefront path, e.g. `/pages/privacy-policy`. */
  link: string;
}

export type FooterStaticPageGroup = "company" | "policies" | "help";

/** Column order of the static-page groups in the footer. */
export const FOOTER_STATIC_PAGE_GROUPS: FooterStaticPageGroup[] = [
  "company",
  "policies",
  "help",
];

export interface FooterData {
  categories: FooterCategory[];
  social_links: FooterLink[];
  bottom_nav_links: FooterLink[];
  app_store_links: FooterLink[];
  payment_methods: FooterLink[];
  static_pages: FooterStaticPageLink[];
}

export const getFooterData = async () => {
  const res = await fetchGlobalInstance<{ data: FooterData }>(
    "/footer",
    undefined,
    true,
  );
  return res.data;
};
