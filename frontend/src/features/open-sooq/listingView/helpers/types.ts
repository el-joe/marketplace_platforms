export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type ClassifiedImage = {
  id: string;
  url: string;
  position: number;
};

export type ClassifiedDetail = {
  listing_number: string;
  slug: string;
  title: { ar: string; en: string };
  description: { ar: string; en: string };
  listing_purpose: "sale" | "rent";
  price: number;
  currency: string;
  price_negotiable: boolean;
  attributes: Record<string, unknown> | null;
  location: { city: { ar: string; en: string } | null };
  category: { id: string; name: { ar: string; en: string } } | null;
  images: ClassifiedImage[];
  seller: {
    type: "vendor" | "individual";
    display_name: string;
  };
  views_count: number;
  expires_at: string | null;
  created_at: string;
};

export type InquiryPayload = {
  message: string;
  contact_phone?: string;
};
