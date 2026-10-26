import React from "react";
import { getMarketerProfile } from "./api";
import Image from "next/image";
import HeroBanner from "./hero-banner";
import ProductCard from "@/src/components/shared/product-card";
import Filters from "./filters";
import Pagination from "@/src/components/shared/pagination";
import SmallScreenFiltersSheet from "./small-screen-filters";

export default async function MarketerProfile({
  slug,
  params,
}: {
  slug: string;
  params: Record<string, string>;
}) {
  const marketerProfile = await getMarketerProfile(
    slug,
    new URLSearchParams(params),
  );
  console.log("new URLSearchParams(params),", new URLSearchParams(params));
  return (
    <div>
      <Image
        src={
          marketerProfile.data.profile.banner_url ??
          "/images/no-image-available-icon.jpg"
          // "https://v2cdn.boutiqaat.com/media/boutiqaat_celebrity/W/e/Web-Ar_2025_03_27_16_37_29_700.jpg"
        }
        alt=""
        width={4400}
        height={1860}
        className="mx-auto mb-4 max-h-[70vh]"
      />
      <div className="container">
        <div className="flex items-start gap-3 relative">
          <div className="lg:min-w-50 xl:min-w-75 hidden lg:block">
            <Filters facets={marketerProfile.data.facets} />
          </div>
          <div className="flex-1 overflow-hidden">
            <HeroBanner banners={marketerProfile.data.banners} />{" "}
            <SmallScreenFiltersSheet facets={marketerProfile.data.facets} />
            <div className="flex flex-wrap gap-3 mt-3">
              {marketerProfile.data.listings.items.map((p) => (
                <ProductCard
                  key={p.listing_id}
                  productData={p}
                  className="h-auto w-[calc((100%-12px)/2)] xl:w-[calc((100%-48px)/5)]"
                />
              ))}
            </div>
            {!marketerProfile.data.listings.items.length && (
              <Image
                src="/images/empty-result.webp"
                alt=""
                width={1280}
                height={1280}
                className="w-md mx-auto"
              />
            )}
            {!!marketerProfile.data.listings.items.length && (
              <Pagination
                totalPages={marketerProfile.data.listings.meta.last_page}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
