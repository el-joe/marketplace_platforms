import { Star } from "lucide-react";
import { cn } from "@/src/lib/utils";

interface RatingStarsProps {
  rating: number;
  maxStars?: number;
  size?: "xs" | "sm" | "md" | "lg";
}

export function RatingStars({
  rating,
  maxStars = 5,
  size = "md",
}: RatingStarsProps) {
  const iconSize = {
    xs: "h-3 w-3",
    sm: "h-4 w-4",
    md: "h-5 w-5",
    lg: "h-7 w-7",
  };
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
              className={`${iconSize[size]} fill-muted stroke-muted-foreground`}
            />

            {/* Filled portion */}
            <div
              className="absolute inset-0 overflow-hidden"
              style={{ width: `${fillPercentage}%` }}
            >
              <Star
                className={`${iconSize[size]} fill-green-3 stroke-green-3`}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}

interface RatingBadgeProps {
  rating: number;
  reviewCount?: number;
  className?: string;
}

/** Compact "⭐ 4.9 (128)" pill — reuses the same star iconography as RatingStars. */
export function RatingBadge({ rating, reviewCount, className }: RatingBadgeProps) {
  return (
    <div
      className={cn(
        "inline-flex items-center gap-1 rounded-full bg-white/95 px-2 py-1 text-xs font-bold text-primary shadow-sm",
        className,
      )}
    >
      <Star className="size-3.5 fill-yellow stroke-yellow" />
      {rating.toFixed(1)}
      {reviewCount !== undefined && (
        <span className="font-medium text-gray">({reviewCount})</span>
      )}
    </div>
  );
}
