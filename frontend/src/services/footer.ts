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

export interface FooterData {
  categories: FooterCategory[];
  social_links: FooterLink[];
  bottom_nav_links: FooterLink[];
  app_store_links: FooterLink[];
  payment_methods: FooterLink[];
}

export const getFooterData = async () => {
  const res = await fetchGlobalInstance<{ data: FooterData }>(
    "/footer",
    undefined,
    true,
  );
  return res.data;
};
