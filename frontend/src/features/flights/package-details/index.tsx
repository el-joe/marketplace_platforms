import { getLocale, getTranslations } from "next-intl/server";
import Image from "next/image";
import { BuildingIcon, TagIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import HeroGallery from "./components/hero-gallery";
import GuidelinesAccordion from "./components/guidelines-accordion";
import PricingTiers from "./components/pricing-tiers";
import BookingSidebar from "./components/booking-sidebar";
import type { TravelPackageDetail } from "../helpers/types";
import type { CurrencyCode } from "@/src/helpers/get-currency-symbol";

type Props = {
  pkg: TravelPackageDetail;
};

export default async function PackageDetails({ pkg }: Props) {
  const locale = await getLocale();
  const t = await getTranslations("flights.packageDetails");

  const localeKey = locale === "ar" ? "ar" : "en";
  const title = pkg.title[localeKey] ?? pkg.title.en;
  const description = pkg.description[localeKey] ?? pkg.description.en ?? "";

  const breadcrumbs = [
    { label: locale === "ar" ? "الرئيسية" : "Home", href: "/" },
    { label: locale === "ar" ? "رحلات" : "Travel", href: "/travel" },
    { label: title, href: "" },
  ];


  return (
    <div>
      <HeroGallery
        images={pkg.images}
        destination={pkg.destination_city}
        country={pkg.destination_country}
        description={description}
      />

      <main className="max-w-[1200px] mx-auto px-4 sm:px-8 py-16 grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left: guidelines */}
        <div className="lg:col-span-8 flex flex-col gap-16">
          <div className="px-1 -mt-8">
            <Breadcrumb list={breadcrumbs} />
          </div>

          {pkg.categories.length > 0 && (
            <div className="flex flex-wrap gap-1.5 -mt-12">
              {pkg.categories.map((cat) => (
                <span
                  key={cat.id}
                  className="flex items-center gap-1 text-xs bg-blue-3/10 text-blue-3 px-2.5 py-1 rounded-full"
                >
                  <TagIcon className="size-3" />
                  {cat.name[localeKey] ?? cat.name.en}
                </span>
              ))}
            </div>
          )}

          <p>{pkg.description[localeKey]}</p>

          <GuidelinesAccordion included={pkg.inclusions} />

          {pkg.agency && (
            <section>
              <h2 className="text-2xl font-bold text-primary mb-8">
                {t("agencyTitle")}
              </h2>
              <Card className="border border-border shadow-sm p-6">
                <div className="flex items-center gap-4">
                  {pkg.agency.logo_url && (
                    <div className="relative size-16 rounded-xl overflow-hidden border border-border shrink-0">
                      <Image
                        src={pkg.agency.logo_url}
                        alt={pkg.agency.name}
                        fill
                        className="object-contain p-1"
                        sizes="64px"
                      />
                    </div>
                  )}
                  <div>
                    <div className="flex items-center gap-2">
                      <BuildingIcon className="size-4 text-gray" />
                      <p className="font-bold text-primary">
                        {pkg.agency.name}
                      </p>
                    </div>
                    {pkg.agency.license_number && (
                      <p className="text-xs text-light mt-1">
                        {t("licenseLabel")} {pkg.agency.license_number}
                      </p>
                    )}
                  </div>
                </div>
              </Card>
            </section>
          )}
        </div>

        {/* Right: booking sidebar */}
        <div className="lg:col-span-4 flex flex-col gap-4">
          <BookingSidebar pkg={pkg} />

          {pkg.price_tiers && pkg.price_tiers.length > 0 && (
            <Card className="border border-border shadow-sm p-6">
              <PricingTiers
                tiers={pkg.price_tiers}
                currency={pkg.currency as CurrencyCode}
              />
            </Card>
          )}
        </div>
      </main>
    </div>
  );
}
