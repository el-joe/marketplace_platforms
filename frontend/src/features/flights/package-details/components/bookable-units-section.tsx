import { getTranslations } from "next-intl/server";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";
import type { BookableUnitSummary } from "../../helpers/types";
import BookableUnitsClient from "./bookable-units-client";

type Props = {
  units: BookableUnitSummary[];
  currency: CurrencyCode;
};

export default async function BookableUnitsSection({ units, currency }: Props) {
  if (units.length === 0) return null;

  const t = await getTranslations("flights.packageDetails");

  return (
    <section>
      <h2 className="text-2xl font-bold text-primary mb-8">{t("unitsTitle")}</h2>
      <BookableUnitsClient units={units} currency={currency} />
    </section>
  );
}
