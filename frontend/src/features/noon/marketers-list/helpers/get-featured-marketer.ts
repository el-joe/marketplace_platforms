import { MarketerCard } from "../api";

interface FeaturedSplit {
  featured: MarketerCard | null;
  rest: MarketerCard[];
}

/** Picks the top performer (by conversions) to spotlight in a bento card; leaves the rest untouched. */
export function getFeaturedMarketer(marketers: MarketerCard[]): FeaturedSplit {
  if (marketers.length < 4) {
    return { featured: null, rest: marketers };
  }

  const top = marketers.reduce((best, current) =>
    current.total_conversions > best.total_conversions ? current : best,
  );

  if (top.total_conversions <= 0) {
    return { featured: null, rest: marketers };
  }

  return {
    featured: top,
    rest: marketers.filter((marketer) => marketer.id !== top.id),
  };
}
