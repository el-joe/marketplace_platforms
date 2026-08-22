import { notFound } from "next/navigation";
import { getLocale } from "next-intl/server";
import {
  EyeIcon,
  MapPinIcon,
  TagIcon,
  CalendarIcon,
  StoreIcon,
  UserIcon,
} from "lucide-react";
import { format } from "date-fns";
import { Separator } from "@/src/components/ui/separator";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import Price from "@/src/components/shared/Price";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import CarouselListings from "@/src/components/shared/carousel-listing";
import ListingGallery from "./components/listing-gallery";
import InquiryDialog from "./components/inquiry-dialog";
import { getClassifiedListing } from "./api/listing.actions";
import { ApiRequestError } from "@/src/lib/utils";

type Props = {
  slug: string;
};

export default async function ClassifiedListingView({ slug }: Props) {
  const locale = await getLocale();

  let listing;
  try {
    listing = await getClassifiedListing(slug);
  } catch (err) {
    if (err instanceof ApiRequestError && err.status === 404) notFound();
    throw err;
  }

  const title = locale === "ar" ? listing.title.ar : listing.title.en;
  const description =
    locale === "ar" ? listing.description.ar : listing.description.en;
  const cityName = listing.location.city
    ? locale === "ar"
      ? listing.location.city.ar
      : listing.location.city.en
    : null;
  const categoryName = listing.category?.name
    ? locale === "ar"
      ? listing.category.name.ar
      : listing.category.name.en
    : null;

  const breadcrumbs = [
    { label: "Home", href: "/" },
    ...(categoryName && listing.category
      ? [{ label: categoryName, href: `/classified/${listing.category.id}` }]
      : []),
    { label: listing.listing_number, href: "#" },
  ];

  const PURPOSE_LABEL: Record<string, string> = {
    sale: locale === "ar" ? "للبيع" : "For Sale",
    rent: locale === "ar" ? "للإيجار" : "For Rent",
  };

  const attributes = listing.attributes
    ? Object.entries(listing.attributes).filter(
        ([, v]) => v !== null && v !== "",
      )
    : [];

  return (
    <div className="container py-4">
      <Breadcrumb list={breadcrumbs} />

      <div className="grid grid-cols-1 lg:grid-cols-22 gap-4 lg:gap-6 mt-4 items-start">
        {/* col one — gallery */}
        <div className="lg:col-span-8 lg:sticky top-28">
          <ListingGallery images={listing.images} title={title} />
        </div>

        {/* col two — core info */}
        <div className="lg:col-span-9">
          <div className="flex items-start gap-2 flex-wrap">
            <span
              className={`text-xs font-semibold px-2.5 py-1 rounded-full ${
                listing.listing_purpose === "rent"
                  ? "bg-blue-100 text-blue-700"
                  : "bg-green-100 text-green-700"
              }`}
            >
              {PURPOSE_LABEL[listing.listing_purpose]}
            </span>
            <span className="text-xs text-gray flex items-center gap-1">
              <EyeIcon className="size-3" />
              {listing.views_count.toLocaleString()} views
            </span>
          </div>

          <h1 className="text-xl font-bold text-primary mt-2 leading-snug">
            {title}
          </h1>

          <div className="flex items-center gap-3 mt-3">
            <Price
              currentPrice={listing.price}
              currency={listing.currency as CurrencyCode}
              size="xl"
            />
            {listing.price_negotiable && (
              <span className="text-xs border border-border rounded-full px-3 py-1 text-gray">
                {locale === "ar" ? "قابل للتفاوض" : "Negotiable"}
              </span>
            )}
          </div>

          <div className="flex flex-wrap gap-3 mt-4 text-sm text-gray">
            {cityName && (
              <span className="flex items-center gap-1.5 bg-gray-2 rounded-full px-3 py-1">
                <MapPinIcon className="size-3.5 shrink-0" />
                {cityName}
              </span>
            )}
            {categoryName && (
              <span className="flex items-center gap-1.5 bg-gray-2 rounded-full px-3 py-1">
                <TagIcon className="size-3.5 shrink-0" />
                {categoryName}
              </span>
            )}
            <span className="flex items-center gap-1.5 bg-gray-2 rounded-full px-3 py-1">
              <CalendarIcon className="size-3.5 shrink-0" />
              {format(new Date(listing.created_at), "dd MMM yyyy")}
            </span>
          </div>

          <Separator className="my-5" />

          {attributes.length > 0 && (
            <div className="mb-5">
              <h2 className="font-bold text-sm mb-3">Details</h2>
              <div className="grid grid-cols-2 gap-2">
                {attributes.map(([key, value]) => (
                  <div key={key} className="bg-gray-2 rounded-xl px-4 py-2.5">
                    <p className="text-xs text-gray capitalize">
                      {key.replace(/_/g, " ")}
                    </p>
                    <p className="text-sm font-semibold mt-0.5 text-primary">
                      {String(value)}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {description && (
            <div>
              <h2 className="font-bold text-sm mb-2">Description</h2>
              <p className="text-sm text-gray leading-relaxed whitespace-pre-line">
                {description}
              </p>
            </div>
          )}
        </div>

        {/* col three — seller card */}
        <div className="lg:col-span-5 flex flex-col gap-4">
          <div className="border border-border rounded-2xl overflow-hidden">
            <div className="p-4">
              <div className="flex items-center gap-3">
                <div className="size-12 rounded-full bg-gray-2 flex items-center justify-center shrink-0">
                  {listing.seller.type === "vendor" ? (
                    <StoreIcon className="size-6 text-gray" />
                  ) : (
                    <UserIcon className="size-6 text-gray" />
                  )}
                </div>
                <div className="min-w-0">
                  <p className="font-bold text-sm text-primary truncate">
                    {listing.seller.display_name}
                  </p>
                  <p className="text-xs text-gray capitalize mt-0.5">
                    {listing.seller.type === "vendor"
                      ? locale === "ar"
                        ? "متجر"
                        : "Store"
                      : locale === "ar"
                        ? "فرد"
                        : "Individual"}
                  </p>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2 mt-4">
                {listing.seller.positive_rating !== null && (
                  <div className="bg-gray-2 rounded-xl px-3 py-2 text-center">
                    <p className="text-xs text-gray">
                      {locale === "ar" ? "تقييم إيجابي" : "Positive Rating"}
                    </p>
                    <p className="font-bold text-green text-sm mt-0.5">
                      {listing.seller.positive_rating}%
                    </p>
                  </div>
                )}
                {listing.seller.member_since !== null && (
                  <div className="bg-gray-2 rounded-xl px-3 py-2 text-center">
                    <p className="text-xs text-gray">
                      {locale === "ar" ? "عضو منذ" : "Member Since"}
                    </p>
                    <p className="font-bold text-primary text-sm mt-0.5">
                      {listing.seller.member_since}
                    </p>
                  </div>
                )}
                <div className="bg-gray-2 rounded-xl px-3 py-2 text-center col-span-2">
                  <p className="text-xs text-gray">
                    {locale === "ar" ? "إعلانات نشطة" : "Active Listings"}
                  </p>
                  <p className="font-bold text-primary text-sm mt-0.5">
                    {listing.seller.active_listings}
                  </p>
                </div>
              </div>
            </div>

            <Separator />

            <div className="p-4 flex flex-col gap-3">
              <InquiryDialog
                slug={listing.slug}
                sellerName={listing.seller.display_name}
              />
            </div>
          </div>

          <div className="bg-gray-2 rounded-xl p-3 text-xs text-gray text-center">
            Listing #{listing.listing_number}
            {listing.expires_at && (
              <span className="block mt-0.5">
                Expires {format(new Date(listing.expires_at), "dd MMM yyyy")}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="mt-12">
        <CarouselListings title="Similar Listings" />
      </div>
    </div>
  );
}
