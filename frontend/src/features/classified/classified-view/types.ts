export interface ClassifiedImage {
  id: string;
  url: string;
  alt?: string;
  isPrimary?: boolean;
}

export interface ClassifiedSeller {
  id: string;
  name: string;
  avatar?: string;
  rating: number;
  reviewsCount: number;
  memberSince: string;
  totalListings: number;
  phone: string;
  phoneMasked: string;
  isVerified?: boolean;
}

export interface ClassifiedSpecItem {
  label: string;
  value: string;
  isLink?: boolean;
  href?: string;
}

export interface FeatureGroup {
  name: string;
  count: number;
  items: string[];
}

export interface RecommendedClassifiedItem {
  id: string;
  title: string;
  specs: string;
  location?: string;
  image: string;
  slug?: string;
  isFavorite?: boolean;
}

export interface ClassifiedDetail {
  id: string;
  listingId: string;
  slug: string;
  titleAr: string;
  titleEn: string;
  price: number;
  currency: string;
  isFavorite: boolean;
  favoritesCount: number;
  rating: number;
  reviewsCount: number;
  promotedBadge?: string;
  quickSpecs: Array<{
    type: "tag" | "speedometer" | "gas" | "dealership";
    label: string;
  }>;
  images: ClassifiedImage[];
  specs: {
    column1: ClassifiedSpecItem[];
    column2: ClassifiedSpecItem[];
  };
  description: string;
  features: FeatureGroup[];
  seller: ClassifiedSeller;
  recommended: RecommendedClassifiedItem[];
}
