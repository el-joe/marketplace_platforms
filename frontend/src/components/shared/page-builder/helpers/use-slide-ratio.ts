"use client";
import { useCallback, useState } from "react";

// Clamp so one odd upload can't turn the mobile card into a sliver or a tower.
const MIN_RATIO = 0.75; // 3:4 portrait
const MAX_RATIO = 2.4; // wide landscape
const DEFAULT_RATIO = 0.8; // 4:5, the recommended mobile banner shape

/**
 * Mobile hero slider card shape. Admins upload mobile banners in different
 * shapes per slider (4:5 portrait on one, ~2:1 on another) and the block config
 * has no mobile ratio. The first slide's image decides the card ratio for the
 * whole slider, so every card in a slider is the same size.
 */
export function useSlideRatio() {
  const [ratio, setRatio] = useState<number>(DEFAULT_RATIO);

  const onFirstSlideLoad = useCallback(
    (event: React.SyntheticEvent<HTMLImageElement>) => {
      const { naturalWidth, naturalHeight } = event.currentTarget;
      if (!naturalWidth || !naturalHeight) return;
      const measured = naturalWidth / naturalHeight;
      setRatio(Math.min(MAX_RATIO, Math.max(MIN_RATIO, measured)));
    },
    [],
  );

  return { ratio, onFirstSlideLoad };
}
