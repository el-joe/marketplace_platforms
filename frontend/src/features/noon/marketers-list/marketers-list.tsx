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
          slidesPerView={2}
          spaceBetween={12}
          breakpoints={{
            576: { slidesPerView: 3 },
            992: { slidesPerView: 4 },
            1200: { slidesPerView: 6 },
          }}
        >
          {list.map((m) => (
            <SwiperSlide key={m.id} className="w-fit!s">
              <MarketerCard data={m} />
            </SwiperSlide>
          ))}
        </Swiper>
      )}
    </div>
  );
}

const MarketerCard = ({ data }: { data: IMarketerItem }) => (
  <Link href={`/marketer/${data.slug}`} className="block group">
    <div className="overflow-hidden mb-2">
      <Image
        src={
          (data.avatar_url ?? data.gender === "male")
            ? "/images/male-avatar.webp"
            : "/images/female-avatar.webp"
        }
        alt=""
        width={600}
        height={600}
        className="group-hover:scale-110 transition duration-500"
      />
    </div>
    <p>{data.name}</p>
  </Link>
);
