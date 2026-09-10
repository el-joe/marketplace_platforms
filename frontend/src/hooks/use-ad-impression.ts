"use client";

import { useEffect, useRef } from "react";
import { PaidAdMeta } from "@/src/components/shared/page-builder/types";
import { queueImpression } from "@/src/services/ads";

const VIEWABILITY_THRESHOLD = 0.5;
const VIEWABILITY_DURATION_MS = 1000;

export function useAdImpression(
  ref: React.RefObject<HTMLElement | null>,
  ad: PaidAdMeta | null | undefined,
) {
  const trackedRef = useRef(false);

  useEffect(() => {
    if (!ad?.id || trackedRef.current) return;
    const el = ref.current;
    if (!el) return;

    let timer: ReturnType<typeof setTimeout> | null = null;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && entry.intersectionRatio >= VIEWABILITY_THRESHOLD) {
          if (!timer) {
            timer = setTimeout(() => {
              trackedRef.current = true;
              queueImpression(ad);
              observer.disconnect();
            }, VIEWABILITY_DURATION_MS);
          }
        } else if (timer) {
          clearTimeout(timer);
          timer = null;
        }
      },
      { threshold: VIEWABILITY_THRESHOLD },
    );

    observer.observe(el);

    return () => {
      if (timer) clearTimeout(timer);
      observer.disconnect();
    };
  }, [ad, ref]);
}
