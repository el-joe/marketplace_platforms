"use client";
import { useQuery } from "@tanstack/react-query";
import { useTranslations } from "next-intl";
import { getCookie } from "cookies-next";
import { ChevronDownIcon } from "lucide-react";
import Dropdown from "@/src/components/shared/Dropdown";
import { Button } from "@/src/components/ui/button";
import { getCountriesService } from "@/src/services/countries";
import useHandleLocale from "@/src/hooks/use-handle-locale";

/**
 * Country selector shown in the "Deliver to" dialog. Shared by the desktop
 * and mobile layouts so both change the storefront country the same way
 * (locale prefix swap via useHandleLocale) — no login required.
 */
export default function CountrySwitcher() {
  const t = useTranslations("header.locationDialog");
  const country = getCookie("country")?.toString() ?? "";
  const { handleChangeCountry } = useHandleLocale();
  const { data: countriesData } = useQuery({
    queryKey: ["countries"],
    queryFn: getCountriesService,
  });
  const current = countriesData?.data.find(
    (c) => c.site_code.toLowerCase() === country.toLowerCase(),
  );

  return (
    <Dropdown
      contentClasses="w-fit! max-h-[60vh] overflow-auto"
      menuProps={{ align: "end" }}
      triggerButton={
        <Button
          className={
            "bg-gray-2 rounded-md text-primary font-semibold text-base shrink-0"
          }
          title={t("changeCountry")}
          aria-label={t("changeCountry")}
        >
          <span className="text-xl leading-none" aria-hidden>
            {current?.flag_emoji || "🏳️"}
          </span>
          {country.toUpperCase()} <ChevronDownIcon />
        </Button>
      }
      items={countriesData?.data.map((c) => ({
        itemLabel: c.name,
        value: c.site_code,
        itemIcon: (
          <span className="text-lg leading-none" aria-hidden>
            {c.flag_emoji || "🏳️"}
          </span>
        ),
      }))}
      listTitle={t("countries")}
      onSelect={(item) => handleChangeCountry(item.value)}
    />
  );
}
