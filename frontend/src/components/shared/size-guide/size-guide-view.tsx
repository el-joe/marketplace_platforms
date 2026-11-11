"use client";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { cn } from "@/src/lib/utils";
import { SizeGuide, SizeGuideUnit } from "./types";
import { useSizeGuideView } from "./use-size-guide-view";

type Props = {
  guide: SizeGuide;
  /** Compact mode (inside the add-to-cart modal) hides intro, steps and diagram. */
  compact?: boolean;
  className?: string;
};

const UNITS: SizeGuideUnit[] = ["cm", "in"];

/**
 * Interactive vendor size table: cm/inch toggle, a "find my size" helper
 * when the guide has a height column, then how-to-measure steps + diagram.
 */
export default function SizeGuideView({ guide, compact = false, className }: Props) {
  const t = useTranslations("sizeGuide");
  const locale = useLocale();
  const {
    unit,
    setUnit,
    heightColumn,
    heightInput,
    setHeightInput,
    recommendedIndex,
    recommendedSize,
    cell,
  } = useSizeGuideView(guide);

  const intro = guide.intro[locale] ?? guide.intro.en;
  const steps = (guide.how_to_measure[locale] ?? guide.how_to_measure.en ?? "")
    .split("\n")
    .map((s) => s.trim())
    .filter(Boolean);

  return (
    <div className={cn("flex flex-col gap-4", className)}>
      {!compact && intro && (
        <p className="text-sm text-gray leading-relaxed whitespace-pre-line">{intro}</p>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        {heightColumn ? (
          <label className="flex items-center gap-2 text-xs md:text-sm">
            <span className="font-medium">{t("findMySize")}</span>
            <input
              type="number"
              inputMode="decimal"
              min={0}
              value={heightInput}
              onChange={(e) => setHeightInput(e.target.value)}
              placeholder={t("yourHeight")}
              className="w-24 border border-border-color rounded-md px-2 py-1"
            />
            <span className="text-gray">{unit}</span>
          </label>
        ) : (
          <span />
        )}
        <div
          role="radiogroup"
          aria-label={t("unit")}
          className="inline-flex rounded-full border border-border-color p-0.5 text-xs"
        >
          {UNITS.map((u) => (
            <button
              key={u}
              type="button"
              role="radio"
              aria-checked={unit === u}
              onClick={() => setUnit(u)}
              className={cn(
                "px-3 py-1 rounded-full transition-colors",
                unit === u ? "bg-blue text-white" : "text-gray",
              )}
            >
              {t(u)}
            </button>
          ))}
        </div>
      </div>

      {recommendedSize && (
        <p className="text-xs md:text-sm rounded-md bg-blue/10 text-blue px-3 py-2">
          {t("recommended", { size: recommendedSize })}
        </p>
      )}

      <div className="overflow-x-auto rounded-lg border border-border-color">
        <table className="min-w-full text-xs md:text-sm text-center">
          <thead className="bg-gray-2">
            <tr>
              <th scope="col" className="px-3 py-2 font-semibold text-start sticky start-0 bg-gray-2">
                {t("size")}
              </th>
              {guide.columns.map((column) => (
                <th key={column.key} scope="col" className="px-3 py-2 font-semibold whitespace-nowrap">
                  {column.label[locale] || column.label.en}
                  {column.is_measurement && (
                    <span className="font-normal text-gray"> ({unit})</span>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {guide.rows.map((row, index) => (
              <tr
                key={row.size}
                className={cn(
                  "border-t border-border-color",
                  index === recommendedIndex && "bg-blue/10 font-semibold",
                )}
              >
                <th scope="row" className="px-3 py-2 text-start sticky start-0 bg-white">
                  {row.size}
                </th>
                {guide.columns.map((column) => (
                  <td key={column.key} className="px-3 py-2 whitespace-nowrap" dir="ltr">
                    {cell(row.values[column.key], column.is_measurement)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {!compact && (steps.length > 0 || guide.image_url) && (
        <section className="flex flex-col md:flex-row gap-4">
          {steps.length > 0 && (
            <div className="flex-1">
              <h3 className="text-sm md:text-base font-semibold mb-2">{t("howToMeasure")}</h3>
              <ol className="list-decimal ps-5 space-y-1.5 text-sm text-gray">
                {steps.map((step) => (
                  <li key={step}>{step}</li>
                ))}
              </ol>
            </div>
          )}
          {guide.image_url && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={guide.image_url}
              alt={t("howToMeasure")}
              className="md:w-64 max-h-80 object-contain rounded-lg border border-border-color bg-white"
            />
          )}
        </section>
      )}
    </div>
  );
}
