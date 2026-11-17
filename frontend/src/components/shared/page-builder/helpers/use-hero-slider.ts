"use client";
import { useCallback, useMemo, useRef, useState } from "react";
import type { Swiper as SwiperInstance } from "swiper";

// Swiper's loop mode needs enough slides to fill both sides of a centered
// active slide; with 2–3 banners it silently disables the loop and the right
// side of the mobile carousel stays empty.
const MIN_LOOP_SLIDES = 6;

/**
 * Hero slider state: repeats the slides when there are too few for Swiper's
 * loop, and reports the active index of the real slide (not the copy), so the
 * dots always show one dot per real banner.
 */
export function useHeroSlider<T>(slides: T[], loop: boolean) {
  const swiperRef = useRef<SwiperInstance | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);
  const count = slides.length;

  const renderedSlides = useMemo(() => {
    if (!loop || count < 2 || count >= MIN_LOOP_SLIDES) {
      return slides.map((slide, index) => ({ slide, index, copy: 0 }));
    }
    const copies = Math.ceil(MIN_LOOP_SLIDES / count);
    return Array.from({ length: copies }).flatMap((_, copy) =>
      slides.map((slide, index) => ({ slide, index, copy })),
    );
  }, [slides, loop, count]);

  const onSwiper = useCallback((swiper: SwiperInstance) => {
    swiperRef.current = swiper;
  }, []);

  const onSlideChange = useCallback(
    (swiper: SwiperInstance) => {
      if (count > 0) setActiveIndex(swiper.realIndex % count);
    },
    [count],
  );

  const goTo = useCallback(
    (index: number) => {
      const swiper = swiperRef.current;
      if (!swiper) return;
      if (swiper.params.loop) swiper.slideToLoop(index);
      else swiper.slideTo(index);
    },
    [],
  );

  return { renderedSlides, activeIndex, onSwiper, onSlideChange, goTo };
}
