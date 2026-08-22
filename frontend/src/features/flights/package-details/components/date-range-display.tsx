import { getTranslations } from "next-intl/server";
import { ArrowRightIcon } from "lucide-react";

type Props = {
  departureDate: string;
  returnDate: string;
};

function formatDate(date: string) {
  return new Date(date).toLocaleDateString("en-US", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export default async function DateRangeDisplay({
  departureDate,
  returnDate,
}: Props) {
  const t = await getTranslations("flights.packageDetails");

  return (
    <div className="p-4 bg-gray-2 rounded-xl flex items-center justify-between">
      <div className="flex flex-col">
        <span className="text-[10px] text-gray uppercase">
          {t("departure")}
        </span>
        <span className="text-sm font-semibold text-primary">
          {formatDate(departureDate)}
        </span>
      </div>
      <ArrowRightIcon className="size-4 text-light/40 rtl:rotate-180" />
      <div className="flex flex-col text-end">
        <span className="text-[10px] text-gray uppercase">{t("return")}</span>
        <span className="text-sm font-semibold text-primary">
          {formatDate(returnDate)}
        </span>
      </div>
    </div>
  );
}
