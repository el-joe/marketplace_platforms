import { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import { Product } from "@/types/globals";
import { MarketerProfileListingItem, MarketerProfileMarketer, MarketerProfileInfo } from "./types";

/**
 * Adapts the lightweight marketer-profile listing shape (from
 * GET /marketers/{slug}) into the full Product shape ProductCard expects.
 * Fields the marketer-profile endpoint doesn't return (vendor, shipping
 * badge, admin/express flags) are filled with the marketer-listing defaults.
 */
export function toProductCard(
  item: MarketerProfileListingItem,
  marketer: MarketerProfileMarketer,
  profile: MarketerProfileInfo,
): Product {
  return {
    listing_id: item.listing_id,
    listing_type: "marketer",
    listing_ref: item.referral_code ?? item.listing_id,
    sku: item.sku,
    vendor_sku: null,
    product_id: item.product_id,
    product_slug: item.product_slug,
    slug: item.product_slug,
    variant_id: item.variant_id,
    variant_slug: item.variant_slug,
    product_url: item.product_url,
    url_param: item.url_param,
    variant_name: item.variant_name,
    variant_image: item.primary_image ?? "",
    primary_image: item.primary_image ?? "",
    name_en: item.name_en,
    name_ar: item.name_ar,
    thumbnail: item.primary_image ?? "",
    images: item.images.map((url, position) => ({
      id: `${item.listing_id}-${position}`,
      url,
      alt: { ar: item.name_ar, en: item.name_en },
      is_primary: position === 0,
      position,
      variant_id: item.variant_id,
    })),
    category_name: item.category_name,
    price: item.price,
    price_formatted: item.price_formatted,
    compare_at_price: item.compare_at_price,
    currency: item.currency as CurrencyCode,
    condition: item.condition,
    is_admin_listing: false,
    is_express_fbn: false,
    fulfillment_model: "marketer",
    vendor: null as unknown as Product["vendor"],
    marketer: {
      id: marketer.id,
      name: marketer.name,
      marketer_type: marketer.marketer_type,
      profile_slug: profile.slug,
      profile_url: profile.profile_url,
    },
    referral_code: item.referral_code,
    referral_link: item.referral_link,
    shipping_badge: null,
    rating_avg: item.rating_avg,
    rating_count: item.rating_count,
    total_sold: item.total_sold,
    is_wishlisted: false,
    is_sponsored: false,
  };
}
