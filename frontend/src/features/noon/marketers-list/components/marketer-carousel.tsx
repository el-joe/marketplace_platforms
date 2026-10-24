"use client";

import { Children, useState, type ReactNode } from "react";
import type { Swiper as SwiperType } from "swiper";
import { Swiper, SwiperSlide } from "swiper/react";
import { A11y } from "swiper/modules";
import { ChevronLeft, ChevronRight } from "lucide-react";
import useLocale from "@/src/hooks/use-locale";
import { CAROUSEL_BREAKPOINTS } from "../helpers/constants";

interface Props {
  label: string;
  prevLabel: string;
  nextLabel: string;
  children: ReactNode;
}

const arrowClass =
  "absolute top-[38%] z-10 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-primary shadow-lg ring-1 ring-black/5 transition hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:pointer-events-none disabled:opacity-0 md:flex";

/** Leaf: Swiper row with start/end arrows; cards are server-rendered and passed in as children. */
export default function MarketerCarousel({ label, prevLabel, nextLabel, children }: Props) {
  const dir = useLocale() === "ar" ? "rtl" : "ltr";
  const [swiper, setSwiper] = useState<SwiperType | null>(null);
  const [state, setState] = useState({ begin: true, end: false, locked: false });

  const sync = (s: SwiperType) =>
    setState({ begin: s.isBeginning, end: s.isEnd, locked: s.isLocked });

  return (
    <div className="relative" role="region" aria-roledescription="carousel" aria-label={label}>
      <Swiper
        dir={dir}
        key={dir}
        modules={[A11y]}
        slidesPerView={2.2}
        spaceBetween={12}
        breakpoints={CAROUSEL_BREAKPOINTS}
        watchOverflow
        onSwiper={(s) => {
          setSwiper(s);
          sync(s);
          s.on("slideChange", sync);
          s.on("resize", sync);
          s.on("lock", sync);
          s.on("unlock", sync);
          s.on("breakpoint", sync);
        }}
      >
        {Children.toArray(children).map((child, i) => (
          <SwiperSlide key={i}>{child}</SwiperSlide>
        ))}
      </Swiper>

      {!state.locked && (
        <>
          <button
            type="button"
            aria-label={prevLabel}
            disabled={state.begin}
            onClick={() => swiper?.slidePrev()}
            className={`${arrowClass} ltr:-left-3 rtl:-right-3`}
          >
            <ChevronLeft className="h-5 w-5 rtl:rotate-180" />
          </button>
          <button
            type="button"
            aria-label={nextLabel}
            disabled={state.end}
            onClick={() => swiper?.slideNext()}
            className={`${arrowClass} ltr:-right-3 rtl:-left-3`}
          >
            <ChevronRight className="h-5 w-5 rtl:rotate-180" />
          </button>
        </>
      )}
    </div>
  );
}
