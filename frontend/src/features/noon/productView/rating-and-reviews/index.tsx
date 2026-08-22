import { Separator } from "@/src/components/ui/separator";

import { IProductDetails } from "../types";
import Rating from "./rating";
import Reviews from "./reviews";
import { getTranslations } from "next-intl/server";

type Props = {
  // ratingAVG: IProductDetails["rating_avg"];
  // ratingCount: IProductDetails["rating_count"];
  // ratingBreakdown: IProductDetails["rating_breakdown"];
  reviews: IProductDetails["reviews"];
};

export default async function RatingAndReviews({
  // ratingAVG,
  // ratingBreakdown,
  // ratingCount,
  reviews,
}: Props) {
  const t = await getTranslations("productView");

  return (
    <>
      <h3 className="py-5 text-2xl font-bold text-light">
        {t("productRatings&Reviews")}
      </h3>
      <Separator />
      <div
        className="grid lg:grid-cols-[230px_1fr] xl:grid-cols-[460px_1fr] gap-4 py-5"
        id="reviews"
      >
        {/* rating */}
        <Rating
          ratingAVG={reviews.rating_avg}
          ratingBreakdown={reviews.rating_breakdown}
          ratingCount={reviews.rating_count}
        />
        <Reviews reviews={reviews.items} />
      </div>
    </>
  );
}
