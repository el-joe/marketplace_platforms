import { RatingStars } from "@/src/components/ui/RatingStars";

import { StarIcon } from "lucide-react";
import { Reviews } from "../types/product-details";
import { getTranslations } from "next-intl/server";
import getStarSColorByCount from "../helpers/get-stars-color-by-count";

type Props = {
  ratingAVG: Reviews["rating_avg"];
  ratingCount: Reviews["rating_count"];
  ratingBreakdown: Reviews["rating_breakdown"];
};

export default async function Rating({
  ratingAVG,
  ratingBreakdown,
  ratingCount,
}: Props) {
  const t = await getTranslations("productView");
  return (
    <div>
      <div className="flex flex-col gap-3 lg:border-e border-border pe-3 lg:mb-7">
        <p className="text-4xl font-bold">{ratingAVG}</p>
        <RatingStars rating={ratingAVG} />
        <p className="text-sm">
          {t("basedOn$ratingsFromTrustedSources", { count: ratingCount })}
        </p>
        {/* ratings metrics list */}
        {ratingBreakdown.map((m, i) => {
          const color = getStarSColorByCount(m.stars);
          return (
            <div className="hidden lg:flex items-center gap-2" key={i}>
              <div className="flex items-center gap-1 w-8">
                <p className="font-semibold">{m.stars}</p>
                <StarIcon
                  className={`w-4`}
                  style={{
                    fill: `var(--color-${color})`,
                    color: `var(--color-${color})`,
                  }}
                />
              </div>
              {/* metric bar */}
              <div className="h-2 bg-gray-2 flex-1 rounded-lg overflow-hidden">
                <span
                  className={`h-full block rounded-lg`}
                  style={{
                    width: `${m.percentage}%`,
                    background: `var(--color-${color})`,
                  }}
                ></span>
              </div>
              <div className="w-8 text-sm">{m.percentage}%</div>
            </div>
          );
        })}
      </div>
      <div className="pe-3 hidden lg:block">
        <div className="flex items-center gap-1 mb-3">
          <div className="w-6 h-6 rounded-full bg-main grid place-items-center border">
            <StarIcon className="w-4 h-4" />
          </div>
          <p className="font-semibold">{t("HowDoIReviewThisProduct?")}</p>
        </div>
        <p className="text-sm text-light">
          {t("HowDoIReviewThisProductDescription")}
        </p>
        <div className="flex items-center gap-1 mt-5 mb-3">
          <div className="w-6 h-6 rounded-full bg-main grid place-items-center border">
            <StarIcon className="w-4 h-4" />
          </div>
          <p className="font-semibold">{t("WhereDoTeReviewsComeFrom?")}</p>
        </div>
        <p className="text-sm text-light">
          {t("WhereDoTeReviewsComeFromDescription")}
        </p>
      </div>
    </div>
  );
}
