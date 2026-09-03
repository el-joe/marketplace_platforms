export interface IRatingBreakdown {
  stars: number;
  percentage: number;
  count?: number;
}

export interface ISellerReview {
  id: string;
  reviewer_name: string;
  avatar_letter?: string;
  is_verified_purchase: boolean;
  rating: number;
  date: string;
  comment: string;
  translated_comment?: string;
  original_language?: string;
}

export interface ISellerProfile {
  id: string;
  store_name: string;
  logo_text?: string;
  address: string;
  email: string;
  seller_rating: number;
  positive_ratings_pct: number;
  customers_count: string;
  customers_period_text: string;
  product_as_described_pct: number;
  seller_since: string;
  total_ratings_count: number;
  total_reviews_count: number;
  rating_breakdown: IRatingBreakdown[];
  reviews: ISellerReview[];
}
