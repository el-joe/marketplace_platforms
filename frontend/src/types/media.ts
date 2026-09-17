export interface ImageAltDTO {
  ar: string | null;
  en: string | null;
}

/** Matches the backend `image`/`images[]` contract introduced in P-17. */
export interface ImageDTO {
  url: string;
  alt?: ImageAltDTO | null;
  is_primary?: boolean;
  position?: number;
  id?: string;
}

/**
 * Any entity that may carry the new image contract, or one of the legacy
 * alias keys the backend still returns for one release (P-17).
 */
export interface ListingImageSource {
  image?: ImageDTO | null;
  images?: (ImageDTO | { url: string })[] | null;
  // TODO: remove legacy alias fallback once backend drops primary_image/thumbnail/variant_image aliases (see enhancement.md P-17)
  primary_image?: string | null;
  primary_image_url?: string | null;
  thumbnail?: string | null;
  thumbnail_url?: string | null;
  variant_image?: string | null;
}

/**
 * Resolves the best available image URL for a listing/product/variant-like
 * entity. Prefers the new `image.url` contract, then the first entry of
 * `images[]`, then falls back through the legacy alias keys so entities that
 * haven't been migrated on the backend yet still render correctly.
 */
export function getListingImage(
  entity: ListingImageSource | null | undefined,
): string {
  if (!entity) return "";

  if (entity.image?.url) return entity.image.url;

  const firstImage = entity.images?.[0];
  if (firstImage?.url) return firstImage.url;

  // TODO: remove legacy alias fallback once backend drops primary_image/thumbnail/variant_image aliases (see enhancement.md P-17)
  return (
    entity.primary_image_url ??
    entity.primary_image ??
    entity.variant_image ??
    entity.thumbnail_url ??
    entity.thumbnail ??
    ""
  );
}

/** Localized alt text for an image, falling back to a provided product name. */
export function getListingImageAlt(
  entity: ListingImageSource | null | undefined,
  locale: "ar" | "en",
  fallbackName?: string | null,
): string {
  const alt = entity?.image?.alt ?? (entity?.images?.[0] as ImageDTO)?.alt;
  return alt?.[locale] || fallbackName || "";
}
