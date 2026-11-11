"use client";
import { useMemo, useState } from "react";
import { convertCell, recommendRow } from "./convert";
import { SizeGuide, SizeGuideUnit } from "./types";

/** Column keys treated as "your height" for the find-my-size helper. */
const HEIGHT_KEYS = ["height", "your_height", "body_height"];

export function useSizeGuideView(guide: SizeGuide) {
  const [unit, setUnit] = useState<SizeGuideUnit>(guide.unit);
  const [heightInput, setHeightInput] = useState("");

  const heightColumn = guide.columns.find((c) => HEIGHT_KEYS.includes(c.key));

  const recommendedIndex = useMemo(() => {
    const height = Number(heightInput.replace(",", "."));
    if (!heightColumn || !heightInput || !Number.isFinite(height) || height <= 0) {
      return null;
    }
    // The customer types in the unit they are viewing; compare in the guide's unit.
    const inGuideUnit = Number(convertCell(String(height), unit, guide.unit));
    return recommendRow(guide, heightColumn.key, inGuideUnit);
  }, [guide, heightColumn, heightInput, unit]);

  const cell = (value: string | undefined, isMeasurement: boolean) =>
    !value ? "—" : isMeasurement ? convertCell(value, guide.unit, unit) : value;

  return {
    unit,
    setUnit,
    heightColumn,
    heightInput,
    setHeightInput,
    recommendedIndex,
    recommendedSize:
      recommendedIndex !== null ? guide.rows[recommendedIndex]?.size : null,
    cell,
  };
}
