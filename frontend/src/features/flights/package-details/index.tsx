import HeroGallery from "./components/hero-gallery";
import GuidelinesAccordion from "./components/guidelines-accordion";
import BookingSidebar from "./components/booking-sidebar";
import type { TravelPackageDetail } from "../helpers/types";

type Props = {
  pkg: TravelPackageDetail;
};

export default function PackageDetails({ pkg }: Props) {
  return (
    <div>
      <HeroGallery
        images={pkg.images}
        destination={pkg.destination_city}
        country={pkg.destination_country}
        description={pkg.description_en ?? ""}
      />

      <main className="max-w-[1200px] mx-auto px-4 sm:px-8 py-16 grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left: guidelines */}
        <div className="lg:col-span-8 flex flex-col gap-16">
          <GuidelinesAccordion included={pkg.inclusions} />
        </div>

        {/* Right: booking sidebar */}
        <div className="lg:col-span-4">
          <BookingSidebar pkg={pkg} />
        </div>
      </main>
    </div>
  );
}
