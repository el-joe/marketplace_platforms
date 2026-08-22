import ClassifiedCard from "./classified-card";
import type { ClassifiedListing } from "../helpers/types";

type Props = {
  listings: ClassifiedListing[];
};

const ClassifiedGrid = ({ listings }: Props) => {
  return (
    <div className="flex flex-col gap-3">
      {listings.map((listing) => (
        <ClassifiedCard key={listing.listing_id} listing={listing} />
      ))}
    </div>
  );
};

export default ClassifiedGrid;
