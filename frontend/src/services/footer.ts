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

const PUBLIC_BASE =
  process.env.NEXT_PUBLIC_API_PUBLIC_URL ?? "/api/public/v1";

/** Shared: cached storefront footer data (categories + footer settings). GET /footer */
export async function getFooterData(): Promise<FooterData> {
  const res = await fetch(`${PUBLIC_BASE}/footer`, {
    next: { revalidate: 300 },
    headers: { Accept: "application/json" },
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  const { data } = (await res.json()) as { data: FooterData };
  return data;
}
