import { getTranslations } from "next-intl/server";
import { HeadphonesIcon, ArrowRightIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import DateRangeDisplay from "./date-range-display";
import ContactModal from "./contact-modal";
import { CONTACT_INFO } from "../../helpers/constants";
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
            currentPrice={pkg.price_cents / 100}
            className="text-blue-3"
          />
          <span className="text-gray text-xs">{t("perPerson")}</span>
        </div>

        <div className="flex flex-col gap-4 mb-8">
          <DateRangeDisplay
            departureDate={pkg.departure_date}
            returnDate={pkg.return_date}
          />
        </div>

        <ContactModal
          packageName={pkg.destination_city}
          email={CONTACT_INFO.email}
          phone={CONTACT_INFO.phone}
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
            packageName={pkg.destination_city}
            email={CONTACT_INFO.email}
            phone={CONTACT_INFO.phone}
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
