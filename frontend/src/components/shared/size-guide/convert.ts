import { SizeGuide, SizeGuideUnit } from "./types";

const CM_PER_INCH = 2.54;
const NUMBER_PATTERN = /\d+(?:[.,]\d+)?/g;

function round(value: number, unit: SizeGuideUnit): string {
  const rounded = unit === "in" ? Math.round(value * 2) / 2 : Math.round(value);
  return String(rounded);
}

/**
 * Converts every number inside a cell (so ranges like "150-155" work) from
 * the unit the vendor entered to the unit the customer picked.
 */
export function convertCell(
  value: string,
  from: SizeGuideUnit,
  to: SizeGuideUnit,
): string {
  if (!value || from === to) return value;
  return value.replace(NUMBER_PATTERN, (match) => {
    const n = Number(match.replace(",", "."));
    const converted = from === "cm" ? n / CM_PER_INCH : n * CM_PER_INCH;
    return round(converted, to);
  });
}

/** Lower/upper bound of a cell such as "150", "150-155" or "150 – 155". */
function bounds(value: string): [number, number] | null {
  const numbers = (value.match(NUMBER_PATTERN) ?? []).map((n) =>
    Number(n.replace(",", ".")),
  );
  if (!numbers.length) return null;
  return [Math.min(...numbers), Math.max(...numbers)];
}

/**
 * Picks the row whose value in `columnKey` contains the given measurement
 * (expressed in the guide's own unit), else the nearest row.
 */
export function recommendRow(
  guide: SizeGuide,
  columnKey: string,
  measurement: number,
): number | null {
  let best: { index: number; distance: number } | null = null;
  guide.rows.forEach((row, index) => {
    const range = bounds(row.values[columnKey] ?? "");
    if (!range) return;
    const [low, high] = range;
    const distance =
      measurement < low ? low - measurement : measurement > high ? measurement - high : 0;
    if (!best || distance < best.distance) best = { index, distance };
  });
  return best ? (best as { index: number }).index : null;
}
