"use client";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";
import { Facets } from "./helpers/types";
import useMarketerFilter from "./helpers/use-marketer-filter";
import { useSearchParams } from "next/navigation";
import useLocale from "@/src/hooks/use-locale";
import { DotIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import PriceFilter from "./price-filter";

type FilterSidebarProps = {
  facets: Facets;
};

const FilterSidebar = ({ facets }: FilterSidebarProps) => {
  const attributes = Object.keys(facets) ?? [];
  const locale = useLocale();

  return (
    <Accordion
      multiple
      defaultValue={attributes}
      dir={locale === "ar" ? "rtl" : "ltr"}
    >
      {/* {Object.entries(facets).map(([key, value])=>
      <ListingFilter key={key} type={key} options={value.}  />
      )} */}
      <ListingFilter
        type={"brands"}
        paramKey="brand"
        options={facets.brands.map((b) => ({
          label: b.name,
          value: b.id,
          count: b.count,
        }))}
      />
      <ListingFilter
        type={"categories"}
        paramKey="category"
        options={facets.categories.map((b) => ({
          label: b.name,
          value: b.id,
          count: b.count,
        }))}
      />
      <ListingFilter
        type={"kinds"}
        paramKey="kind"
        options={facets.kinds.map((b) => ({
          label: b.key,
          value: b.key,
          count: b.count,
        }))}
      />
      <ListingFilter
        type={"sources"}
        paramKey="source"
        options={facets.sources.map((b) => ({
          label: b.key,
          value: b.key,
          count: b.count,
        }))}
      />

      <PriceFilter priceRange={facets?.price} />
    </Accordion>
  );
};

const ListingFilter = ({
  type,
  paramKey,
  options,
}: {
  type: string;
  paramKey: string;
  options: { label: string; value: string; count: number }[];
}) => {
  const searchParams = useSearchParams();
  const { setFilter } = useMarketerFilter();
  const t = useTranslations("marketerProfile");

  const current = searchParams.get(paramKey) ?? "";

  options.forEach((o) => {
    if (o.label === "all") {
      o.value = "";
    }
  });

  if (!options.find((o) => o.label === "all")) {
    options.unshift({
      label: "all",
      count: options.reduce((acc, cur) => (acc += cur.count), 0),
      value: "",
    });
  }

  return (
    <AccordionItem value={type} className="border-b-0! scrollbar-hide">
      <AccordionTrigger className={"font-bold flex items-center"}>
        {t(type)}
        {current && (
          <p className="text-green ms-1 flex items-center">
            {t("active")} <DotIcon />
          </p>
        )}
      </AccordionTrigger>
      <AccordionContent>
        <div className="flex flex-col gap-1.5 py-1">
          {options.map((opt) => {
            const isSelected = current === opt.value;
            return (
              <label
                key={opt.value || "all"}
                // className="flex cursor-pointer items-center gap-2 py-1 text-sm text-primary"
                className={[
                  "flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm transition-colors",
                  isSelected
                    ? "bg-primary/10 font-semibold text-primary"
                    : "text-primary hover:bg-gray-2",
                ].join(" ")}
              >
                <input
                  type="radio"
                  checked={isSelected}
                  onChange={() => setFilter(paramKey, opt.value)}
                  className="size-4 accent-main"
                />
                <span className="min-w-0 flex-1 truncate"> {opt.label}</span>
                {opt.count != null && (
                  <span className="text-xs text-gray">{opt.count}</span>
                )}
              </label>
            );
          })}
        </div>
      </AccordionContent>
    </AccordionItem>
  );
};

export default FilterSidebar;
