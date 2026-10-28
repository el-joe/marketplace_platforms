"use client";
import React from "react";
import { IMarketerItem } from "./helpers/types";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";
import { useTranslations } from "next-intl";
import { Navigation } from "swiper/modules";
import { useSearchParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { getImageURL } from "@/src/helpers/get-image-url";

type Props = {
  list: IMarketerItem[];
  type: string;
};

export default function MarketersList({ list, type }: Props) {
  const t = useTranslations("marketers");
  const searchParams = useSearchParams();

  const isTypeSelected = searchParams.has("type");

  const linkParams = new URLSearchParams(searchParams.toString());

  if (isTypeSelected) {
    linkParams.delete("type");
  } else {
    linkParams.set("type", type);
  }

  const targetHref = `?${linkParams.toString()}`;

  return (
    <div>
      <div className="py-2 flex justify-between items-center">
        <h2 className="text-lg font-bold">{t(type)}</h2>

        <Link href={targetHref} className="flex items-center">
          {isTypeSelected ? (
            <>
              <ArrowLeft className="size-4" /> {t("back")}
            </>
          ) : (
            t("viewAll")
          )}
        </Link>
      </div>

      {isTypeSelected ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
          {list.map((m) => (
            <MarketerCard key={m.id} data={m} />
          ))}
        </div>
      ) : (
        <Swiper
          modules={[Navigation]}
          navigation
          // slidesPerView={2}
          slidesPerView={"auto"}
          spaceBetween={12}
          // breakpoints={{
          //   576: { slidesPerView: 3 },
          //   992: { slidesPerView: 4 },
          //   1200: { slidesPerView: 6 },
          // }}
        >
          {list.map((m) => (
            <SwiperSlide key={m.id} className="w-fit!">
              <MarketerCard data={m} />
            </SwiperSlide>
          ))}
        </Swiper>
      )}
    </div>
  );
}

const FALLBACK_AVATAR = {
  male: "/images/male-avatar.webp",
  female: "/images/female-avatar.webp",
} as const;

const MarketerCard = ({ data }: { data: IMarketerItem }) => (
  <Link
    href={`/marketer/${data.slug}`}
    className="block group w-40 md:w-45 lg:w-65 xl:w-75"
  >
    <div className="relative aspect-square overflow-hidden mb-2 border border-border p-0.5">
      <Image
        src={
          data.avatar_url
            ? getImageURL(data.avatar_url)
            : FALLBACK_AVATAR[data.gender === "male" ? "male" : "female"]
        }
        alt={data.name}
        fill
        sizes="(min-width: 1280px) 300px, (min-width: 1024px) 260px, 180px"
        className="object-cover group-hover:scale-110 transition duration-500"
      />
    </div>
    <p>{data.name}</p>
  </Link>
);
