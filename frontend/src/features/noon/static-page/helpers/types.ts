export type Bilingual = { ar: string | null; en: string | null };

export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

/** GET /static-pages/{slug} — an admin-managed content page (About Us, Terms, Privacy, ...). */
export type StaticPage = {
  id: string;
  slug: string;
  title: Bilingual;
  /** Sanitized (allow-listed) HTML from the admin rich-text editor. */
  content: Bilingual;
  seo: {
    /** Null per language when not set — fall back to `title`. */
    title: Bilingual;
    description: Bilingual;
  };
  footer_group: "company" | "policies" | "help" | null;
  updated_at: string | null;
};
