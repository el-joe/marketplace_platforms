/**
 * Minimal shared listing shape — reflects what the marketplace listing API returns.
 * Full detail shape is IProductDetails in src/features/noon/productView/types/index.ts.
 */
export interface IListing {
  id: string; // UUID
  listing_ref: string;
  name_en: string;
  name_ar: string;
  price: number; // already in major currency units
  currency: string;
  thumbnail: string | null;
  is_wishlisted: boolean;
  vendor: {
    id: string;
    store_name: string;
  } | null;
}
