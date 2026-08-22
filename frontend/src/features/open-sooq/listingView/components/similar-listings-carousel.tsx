import { getTranslations } from "next-intl/server";
import ClassifiedCard from "@/src/features/open-sooq/browse/components/classified-card";
import SectionTitle from "@/src/features/noon/home/section-title";
import type { ClassifiedListing } from "@/src/features/open-sooq/browse/helpers/types";

type Props = {
  listings: ClassifiedListing[];
};

export default async function SimilarListingsCarousel({ listings }: Props) {
  if (!listings.length) return null;

  const t = await getTranslations("openSooq.listingView");

  return (
    <div className="mt-12">
      <SectionTitle title={t("similarListings")} />
      <div className="mt-4 flex flex-col gap-3 sm:grid sm:grid-cols-2 lg:grid-cols-3">
        {listings.slice(0, 6).map((listing) => (
          <ClassifiedCard key={listing.listing_id} listing={listing} />
        ))}
      </div>
    </div>
  );
}
