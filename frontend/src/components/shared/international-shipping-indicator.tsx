import { GlobeIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import { InternationalShippingIndicator as InternationalShippingIndicatorType } from "@/types/globals";

type Props = {
  data: InternationalShippingIndicatorType | null | undefined;
  size?: "sm" | "md";
};

/**
 * docs/plans/international_product_shipping.md Phase 6.
 *
 * "Ships from {origin country}" + ETA range + "customs included" (DDP) note.
 * Renders nothing when `data` is null/undefined — every domestic listing
 * (the default) degrades to no extra UI at all.
 */
const InternationalShippingIndicator = ({ data, size = "md" }: Props) => {
  const locale = useLocale();
  const t = useTranslations("productView");

  if (!data) return null;

  const countryName = data.origin_country?.name?.[locale] ?? data.origin_country?.name?.en ?? "";

  return (
    <div
      className={`flex flex-col gap-0.5 ${size === "sm" ? "text-[9px] md:text-xs" : "text-sm"}`}
    >
      <div className="flex items-center gap-1 text-gray font-medium">
        <GlobeIcon className={size === "sm" ? "size-3" : "size-4"} />
        <span>{t("shipsFrom", { country: countryName })}</span>
      </div>
      <div className="flex flex-wrap items-center gap-x-2 text-gray">
        <span>
          {data.min_eta_days === data.max_eta_days
            ? t("internationalEtaExact", { days: data.max_eta_days })
            : t("internationalEtaRange", {
                min: data.min_eta_days,
                max: data.max_eta_days,
              })}
        </span>
        {data.customs_included && <span>· {t("customsIncluded")}</span>}
      </div>
    </div>
  );
};

export default InternationalShippingIndicator;
