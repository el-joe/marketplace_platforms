import { Star } from "lucide-react";
import { getRatingStarColor } from "./helpers/get-rating-star-color";

interface ProductDetailsRateProps {
  rating: number;
  maxStars?: number;
  size?: "xs" | "sm" | "md" | "lg";
}

export function ProductDetailsRate({
  rating,
  maxStars = 5,
  size = "md",
}: ProductDetailsRateProps) {
  const iconSize = {
    xs: "h-3 w-3",
    sm: "h-4 w-4",
    md: "h-5 w-5",
    lg: "h-7 w-7",
  };
  const starColor = getRatingStarColor(rating);
  return (
    <div className="flex items-center gap-1">
      {Array.from({ length: maxStars }).map((_, index) => {
        const ceilRating = Math.ceil(rating * 2) / 2;
        const fillPercentage = Math.max(
          0,
          Math.min(100, (ceilRating - index) * 100),
        );

        return (
          <div key={index} className={`relative ${iconSize[size]}`}>
            {/* Empty star */}
            <Star
              className={`${iconSize[size]} fill-[#DADDE3] stroke-transparent`}
            />

            {/* Filled portion */}
            <div
              className="absolute inset-0 overflow-hidden"
              style={{ width: `${fillPercentage}%` }}
            >
              <Star
                className={iconSize[size]}
                style={{ fill: starColor, stroke: starColor }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}
