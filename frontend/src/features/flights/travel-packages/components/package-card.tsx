import Image from "next/image";
import { CalendarIcon, UsersIcon, SunIcon, MoonIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { buttonVariants } from "@/src/components/ui/button";
import Card from "@/src/components/shared/Card";
import { cn } from "@/src/lib/utils";
import type { TravelPackageSummary } from "../../helpers/types";
import useLocale from "@/src/hooks/use-locale";

type Props = {
  pkg: TravelPackageSummary;
};

export default function TravelPackageCard({ pkg }: Props) {
  const t = useTranslations("flights.packageCard");

  const locale = useLocale();

  const formattedDate = new Date(pkg.departure_date).toLocaleDateString(
    "en-US",
    {
      day: "numeric",
      month: "short",
      year: "numeric",
    },
  );

  return (
    <Card className="overflow-hidden flex flex-col shadow-sm border border-border">
      <div className="relative h-52">
        <Image
          src={pkg.thumbnail}
          alt={pkg.title_en}
          fill
          sizes="(max-width: 768px) 100vw, 400px"
          className="object-cover"
        />
      </div>

      <div className="flex flex-col gap-3 p-4 flex-1">
        <div>
          <h3 className="font-bold text-base leading-tight text-primary">
            {pkg[`title_${locale}`]}
          </h3>
        </div>

        <div className="flex flex-wrap gap-1.5">
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <CalendarIcon className="size-3 shrink-0" />
            {formattedDate}
          </span>
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <UsersIcon className="size-3 shrink-0" />
            {pkg.seats_remaining} {t("seatsLeft")}
          </span>
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <SunIcon className="size-3 shrink-0" />
            {pkg.duration_days} {t("days")}
          </span>
          <span className="inline-flex items-center gap-1 bg-gray-2 rounded-full px-2.5 py-1 text-xs font-medium text-light">
            <MoonIcon className="size-3 shrink-0" />
            {pkg.duration_nights} {t("nights")}
          </span>
        </div>

        <p className="text-sm text-light">{pkg.agency_name}</p>

        <div className="flex items-end justify-between mt-auto pt-3 border-t border-border">
          <div>
            <p className="text-xs text-gray mb-0.5">{t("from")}</p>
            <p className="font-bold text-blue-3 text-xl leading-none">
              {pkg.currency} {pkg.price_formatted}
            </p>
            <p className="text-xs text-gray mt-0.5">{t("perPerson")}</p>
          </div>
          <Link
            href={`/travel/${pkg.slug}`}
            className={cn(
              buttonVariants({ size: "sm" }),
              "bg-blue-3 hover:bg-blue text-white border-transparent",
            )}
          >
            {t("viewDetails")}
          </Link>
        </div>
      </div>
    </Card>
  );
}
