import { getTranslations } from "next-intl/server";
import { HeadphonesIcon, ArrowRightIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import DateRangeDisplay from "./date-range-display";
import ContactModal from "./contact-modal";
import type { TravelPackageDetail } from "../../helpers/types";

type Props = {
  pkg: TravelPackageDetail;
};

export default async function BookingSidebar({ pkg }: Props) {
  const t = await getTranslations("flights.packageCard");
  const td = await getTranslations("flights.packageDetails");

  return (
    <div className="lg:sticky lg:top-28 flex flex-col gap-6">
      <Card className="border border-border shadow-xl p-8">
        <div className="mb-6">
          <span className="text-gray text-xs uppercase tracking-widest block mb-1">
            {t("from")}
          </span>
          <Price
            size="xl"
            currentPrice={pkg.price}
            className="text-blue-3"
          />
          <span className="text-gray text-xs">{t("perPerson")}</span>
          {pkg.seats_remaining !== null && pkg.seats_remaining > 0 && (
            <div className="flex items-center gap-1.5 mt-2">
              <span
                className={`inline-block size-2 rounded-full ${
                  pkg.seats_remaining <= 5 ? "bg-red" : "bg-green"
                }`}
              />
              <span className="text-xs text-gray">
                {pkg.seats_remaining <= 5
                  ? `Only ${pkg.seats_remaining} seats left!`
                  : `${pkg.seats_remaining} seats available`}
              </span>
            </div>
          )}
          {pkg.seats_remaining === 0 && (
            <p className="text-xs text-red font-semibold mt-2">Sold Out</p>
          )}
        </div>

        <div className="flex flex-col gap-4 mb-8">
          <DateRangeDisplay
            departureDate={pkg.departure_date}
            returnDate={pkg.return_date}
          />
        </div>

        <ContactModal
          packageSlug={pkg.slug}
          packageName={pkg.destination_city}
          email={pkg.agency?.contact_email ?? null}
          phone={pkg.agency?.contact_phone ?? null}
        />

        <div className="mt-6 flex justify-center gap-4 opacity-50">
          <span className="text-[10px] uppercase font-bold tracking-tighter text-light">
            {td("badgeIata")}
          </span>
          <span className="text-[10px] uppercase font-bold tracking-tighter text-light">
            {td("badgeLuxuryGold")}
          </span>
        </div>
      </Card>

      <Card className="border border-border shadow-sm p-6 relative overflow-hidden group">
        <div className="relative z-10">
          <h4 className="font-bold text-primary mb-2">
            {td("conciergeTitle")}
          </h4>
          <p className="text-xs text-light leading-relaxed mb-4">
            {td("conciergeBody")}
          </p>
          <ContactModal
            packageSlug={pkg.slug}
            packageName={pkg.destination_city}
            email={pkg.agency?.contact_email ?? null}
            phone={pkg.agency?.contact_phone ?? null}
            trigger={
              <button className="text-xs text-blue-3 font-bold flex items-center gap-1 group-hover:gap-2 transition-all cursor-pointer">
                {td("conciergeCta")}
                <ArrowRightIcon className="size-3.5 rtl:rotate-180" />
              </button>
            }
          />
        </div>
        <HeadphonesIcon className="absolute -inset-e-4 -bottom-4 size-24 text-gray-2 -rotate-12 group-hover:rotate-0 transition-transform" />
      </Card>
    </div>
  );
}
