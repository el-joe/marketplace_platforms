import { useQueryState } from "nuqs";
import { useMemo } from "react";
import { IProductDetails } from "../types";

export default function useFilterReviews(reviews: IProductDetails["reviews"]["items"]) {
  const [reviewSortParam, setReviewSortParam] = useQueryState("review_sort");
  const [reviewRatingParam, setReviewRatingParam] =
    useQueryState("review_rating");

  const setReviewFilter = ({
    sort,
    rating,
  }: {
    sort?: string;
    rating?: string;
  }) => {
    if (sort) setReviewSortParam(sort);
    if (rating) setReviewRatingParam(rating);
  };

  const filteredReviews = useMemo(() => {
    let result = [...reviews]; // don't mutate the original array

    if (reviewRatingParam) {
      const rating = +reviewRatingParam;
      const isValid = !Number.isNaN(rating) && rating >= 1 && rating <= 5;

      if (isValid) {
        result = result.filter((r) => r.rating >= rating);
      } else {
        setReviewRatingParam(null);
      }
    }

    if (reviewSortParam) {
      switch (reviewSortParam) {
        case "recent":
          result.sort(
            (a, b) =>
              new Date(b.created_at).getTime() -
              new Date(a.created_at).getTime(),
          );
          break;
        case "heigh": // consider renaming to "high" if nothing else depends on the current param value
          result.sort((a, b) => b.rating - a.rating);
          break;
        case "low":
          result.sort((a, b) => a.rating - b.rating);
          break;
        default:
          result.sort((a, b) => b.helpful_count - a.helpful_count);
          setReviewSortParam(null);
      }
    }

    return result;
  }, [
    reviewRatingParam,
    reviewSortParam,
    reviews,
    setReviewRatingParam,
    setReviewSortParam,
  ]);

  return {
    filteredReviews,
    setReviewFilter,
    filtersValue: { sort: reviewSortParam, rating: reviewRatingParam },
  };
}
