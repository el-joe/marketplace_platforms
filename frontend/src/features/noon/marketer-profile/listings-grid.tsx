"use client";
import Image from "next/image";
import { useTranslations } from "next-intl";
import ProductCard from "@/src/components/shared/product-card";
import TravelPackageCard from "@/src/components/shared/travel-package-card";
import {
  ProfileKind,
  ProfileListingItem,
  ProfileProductItem,
  ListingSection,
  TravelListingItem,
} from "./helpers/types";

const SECTION_BADGE_KEYS: Record<ListingSection, string> = {
  own: "badgeOwn",
  vendor_campaign: "badgeVendorCampaign",
  marketer_campaign: "badgeMarketerCampaign",
};

const isTravel = (item: ProfileListingItem): item is TravelListingItem =>
  (item as TravelListingItem).kind === "travel";

type Props = {
  items: ProfileListingItem[];
  kind: ProfileKind;
};

export default function ListingsGrid({ items, kind }: Props) {
  const t = useTranslations("marketerProfile");

  if (!items.length) {
    return (
      <div className="flex flex-col items-center py-10 text-center">
        <Image
          src="/images/empty-result.webp"
          alt=""
          width={1280}
          height={1280}
          className="mx-auto w-64"
        />
        <p className="mt-2 font-bold">{t("noResults")}</p>
        <p className="text-sm text-gray">{t("noResultsHint")}</p>
      </div>
    );
  }

  // Trip cards carry more text than product cards, so they get wider cells.
  if (kind === "travel") {
    return (
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {items.filter(isTravel).map((item) => (
          <TravelPackageCard key={item.listing_id} pkg={item} />
        ))}
      </div>
    );
  }

  return (
    <div className="flex flex-wrap gap-3">
      {items
        .filter((item): item is ProfileProductItem => !isTravel(item))
        .map((p) => (
          <ProductCard
            key={p.listing_id}
            productData={p}
            sectionBadge={
              kind === "products" && p.section
                ? t(SECTION_BADGE_KEYS[p.section])
                : undefined
            }
            className="h-auto w-[calc((100%-12px)/2)] sm:w-[calc((100%-24px)/3)] lg:w-[calc((100%-36px)/4)] xl:w-[calc((100%-48px)/5)]"
          />
        ))}
    </div>
  );
}
