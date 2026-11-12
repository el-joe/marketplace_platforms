import { fetchGlobalInstance, fetchInstance } from "../lib/utils";

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

/**
 * Footer for the visitor's country: static pages (About, Terms, Contact, ...)
 * are per-country, with global pages as the fallback. Falls back to the global
 * footer if the country-scoped call fails (e.g. no/unknown country cookie).
 */
export const getFooterData = async () => {
  try {
    const res = await fetchInstance<{ data: FooterData }>(
      "/footer",
      undefined,
      true,
    );
    return res.data;
  } catch {
    const res = await fetchGlobalInstance<{ data: FooterData }>(
      "/footer",
      undefined,
      true,
    );
    return res.data;
  }
};
