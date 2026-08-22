"use client";
import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import { Field, FieldLabel } from "@/src/components/ui/field";
import { RatingStars } from "@/src/components/ui/RatingStars";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Separator } from "@/src/components/ui/separator";
import {
  ArrowDownUpIcon,
  CircleCheckIcon,
  FilterIcon,
  LanguagesIcon,
  StarIcon,
  ThumbsUpIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";
import React from "react";
import { Navigation } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";
import { IProductDetails } from "../types";
import getStarSColorByCount from "../helpers/get-stars-color-by-count";
import useFilterReviews from "../helpers/use-filter-reviews";

const customerPhotos = [
  "https://f.nooncdn.com/review/0a0884b6-e511-469e-8ed3-d82eec385fa8-1772700833-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-2.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-3.png",
  "https://f.nooncdn.com/review/0a0884b6-e511-469e-8ed3-d82eec385fa8-1772700833-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-2.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-3.png",
  "https://f.nooncdn.com/review/0a0884b6-e511-469e-8ed3-d82eec385fa8-1772700833-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-1.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-2.png",
  "https://f.nooncdn.com/review/0a0e1cfd-e532-4bd7-a952-946439e42775-1771333788-3.png",
];

type Props = {
  reviews: IProductDetails["reviews"]["items"];
};

export default function Reviews({ reviews }: Props) {
  const t = useTranslations("productView");
  const { filteredReviews, setReviewFilter, filtersValue } =
    useFilterReviews(reviews);
  const LIST_FILTER_BY = [
    { label: t("allStars"), value: "all" },
    { label: <StarsStack count={5} />, value: "5" },
    { label: <StarsStack count={4} />, value: "4" },
    { label: <StarsStack count={3} />, value: "3" },
    { label: <StarsStack count={2} />, value: "2" },
    { label: <StarsStack count={1} />, value: "1" },
  ];
  const LIST_SORT_BY = [
    { label: t("topReviews"), value: "top" },
    { label: t("mostRecent"), value: "recent" },
    { label: t("highestRating"), value: "high" },
    { label: t("lowestRating"), value: "low" },
  ];
  if (!reviews.length) {
    return (
      <div className=" flex flex-col items-center justify-center gap-3">
        <Image
          src={"/images/no-reviews-found.svg"}
          alt=""
          width={140}
          height={90}
        />
        <p>{t("noReviewsFound")}</p>
      </div>
    );
  }
  return (
    <div className="lg:ps-6">
      {/* reviews header */}
      <div className="flex items-center gap-2 mb-5">
        <h3 className="hidden lg:block text-base xl:text-xl font-bold">
          {t("allReviews")}
          <span className="text-gray">({reviews.length})</span>
        </h3>
        {/* small screen heading "all images" */}
        <h3 className="lg:hidden text-xl font-bold">
          {t("allReviewsImages")}
          <span className="text-gray">({reviews.length})</span>
        </h3>
        <Button
          className={
            "h-auto bg-light-blue text-blue self-stretch min-h-12 px-2 xl:px-6 ms-auto text-sm hover:border-blue hidden lg:flex"
          }
        >
          <LanguagesIcon /> {t("translateAllReviews")}
        </Button>
        <Field className="w-fit xl:flex-row gap-2 hidden lg:flex">
          <FieldLabel className="text-sm">
            <FilterIcon className="w-4" />
            {t("filterBy")}
          </FieldLabel>
          <Select
            items={LIST_FILTER_BY}
            onValueChange={(v) => setReviewFilter({ rating: v as string })}
            defaultValue={filtersValue.rating ?? "all"}
            triggerClass="min-w-[142px]!"
          />
        </Field>
        <Field className="w-fit xl:flex-row gap-2 hidden lg:flex">
          <FieldLabel className="text-sm">
            <ArrowDownUpIcon className="w-4" />
            {t("sortBy")}
          </FieldLabel>
          <Select
            items={LIST_SORT_BY}
            onValueChange={(v) => setReviewFilter({ sort: v as string })}
            defaultValue={filtersValue.sort ?? "top"}
            triggerClass="min-w-[142px]!"
          />
        </Field>
      </div>
      {/* all customer photos */}
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={"auto"}
        className="w-full! mb-5!"
        spaceBetween={12}
      >
        {customerPhotos.map((photo, i) => (
          <SwiperSlide key={i} className="w-fit!">
            <Image
              src={photo}
              alt="product photo"
              width={120}
              height={120}
              className="rounded-lg aspect-square w-24 lg:w-30"
            />
          </SwiperSlide>
        ))}
      </Swiper>
      <h3 className="lg:hidden text-xl font-bold mb-3">
        {t("allReviews")}
        <span className="text-gray">(185)</span>
      </h3>
      {/* reviews list */}
      {filteredReviews.map((review) => (
        <React.Fragment key={review.id}>
          <div className="flex flex-col gap-3">
            {/* review head */}
            <div className="flex items-start gap-3">
              <div className="w-11 h-11 rounded-full bg-gray-2 grid place-items-center text-gray">
                {review.reviewer_name.split("")[0]}
              </div>
              <div>
                <p className="text-light text-sm pe-3 border-e border-border">
                  {review.reviewer_name}
                </p>
                <p className="text-gray text-xs pe-3">
                  {new Date(review.created_at).toLocaleDateString()}
                </p>
              </div>
              <div className="px-1 rounded-full flex items-center gap-1 bg-gray-2 text-light text-xs">
                <CircleCheckIcon className="w-4" />
                <p className="">{t("verifiedPurchase")}</p>
              </div>
            </div>
            {/* rating */}
            <RatingStars rating={review.rating} size="xs" />
            {/* images */}
            {/* {!!review.images?.length && (
              <div className="flex flex-wrap gap-2 items-center">
                {review.images.map((image, i) => (
                  <Image
                    key={i}
                    src={image}
                    alt="review image"
                    className="aspect-square rounded-lg"
                    width={52}
                    height={52}
                  />
                ))}
              </div>
            )} */}
            {/* product color and link */}
            <div className="flex items-center">
              {/* <p className="text-sm text-gray">
                {t("color")}:{" "}
                <span className="font-semibold">{review.variant}</span>
              </p> */}
              <Separator orientation="vertical" className={"mx-2"} />
              <Link href={"#"} className="text-blue font-semibold">
                {t("viewProduct")}
              </Link>
            </div>
            {/* product overview */}
            <p className="text-lg font-bold text-light -mt-2">
              {review?.title}
            </p>
            {/* review */}
            <p className="text-sm text-gray">{review.body}</p>
            {/* translate button */}
            <Button className={"text-blue text-sm underline w-fit"}>
              {t("translateToEnglish")}
            </Button>
            {/* helpful button (like) */}
            <Button className={"border border-gray text-gray w-fit"}>
              <ThumbsUpIcon /> {t("helpful")} ({review.helpful_count})
            </Button>
          </div>
          <Separator className={"last:hidden my-4"} />
        </React.Fragment>
      ))}
    </div>
  );
}

const StarsStack = ({ count }: { count: number }) => {
  const color = getStarSColorByCount(count);
  return (
    <div className="flex items-center gap-1">
      {Array.from({ length: count }).map((e, i) => (
        <StarIcon
          key={i}
          className={`w-4`}
          style={{
            fill: color,
            color: color,
          }}
        />
      ))}
    </div>
  );
};
