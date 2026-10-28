"use client";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/src/components/ui/accordion";
import { Facets, ProfileKind } from "./helpers/types";
import useMarketerFilter from "./helpers/use-marketer-filter";
import { useSearchParams } from "next/navigation";
import useLocale from "@/src/hooks/use-locale";
import { DotIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import PriceFilter from "./price-filter";

type FilterSidebarProps = {
  facets: Facets;
  kind: ProfileKind;
};

type Option = { label: string; value: string; count: number };

const SOURCE_LABEL_KEYS: Record<string, string> = {
  all: "sourceAll",
  own: "badgeOwn",
  vendor_campaign: "vendorCampaignListings",
  marketer_campaign: "marketerCampaignListings",
};

const FilterSidebar = ({ facets, kind }: FilterSidebarProps) => {
  const locale = useLocale();
  const t = useTranslations("marketerProfile");
  const isProducts = kind === "products";

  const toOptions = (
    list: { id: string; name: string; count: number }[],
  ): Option[] =>
    list.map((o) => ({ label: o.name, value: o.id, count: o.count }));

  const sections = [
    isProducts && (
      <ListingFilter
        key="sources"
        type="sources"
        paramKey="source"
        options={facets.sources.map((s) => ({
          label: t(SOURCE_LABEL_KEYS[s.key] ?? "sourceAll"),
          value: s.key === "all" ? "" : s.key,
          count: s.count,
        }))}
      />
    ),
    kind !== "travel" && (
      <ListingFilter
        key="categories"
        type="categories"
        paramKey="category"
        options={toOptions(facets.categories)}
      />
    ),
    isProducts && (
      <ListingFilter
        key="brands"
        type="brands"
        paramKey="brand"
        options={toOptions(facets.brands)}
      />
    ),
    kind === "travel" && (
      <ListingFilter
        key="destinations"
        type="destinations"
        paramKey="destination"
        options={toOptions(facets.destinations ?? [])}
      />
    ),
    isProducts && facets.discounted_count > 0 && (
      <DiscountFilter key="discount" count={facets.discounted_count} />
    ),
    <PriceFilter key="price" priceRange={facets.price} />,
  ].filter(Boolean);

  return (
    <Accordion
      multiple
      defaultValue={[
        "sources",
        "categories",
        "brands",
        "destinations",
        "discount",
        "price",
      ]}
      dir={locale === "ar" ? "rtl" : "ltr"}
    >
      {sections}
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
  options: Option[];
}) => {
  const searchParams = useSearchParams();
  const { setFilter } = useMarketerFilter();
  const t = useTranslations("marketerProfile");

  const current = searchParams.get(paramKey) ?? "";

  // Nothing to choose from: hide the section (the "all" option alone is noise).
  if (!options.length) return null;

  const withAll: Option[] = options.some((o) => o.value === "")
    ? options
    : [
        {
          label: t("sourceAll"),
          value: "",
          count: options.reduce((acc, cur) => acc + cur.count, 0),
        },
        ...options,
      ];

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
          {withAll.map((opt) => {
            const isSelected = current === opt.value;
            return (
              <label
                key={opt.value || "all"}
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

const DiscountFilter = ({ count }: { count: number }) => {
  const searchParams = useSearchParams();
  const { setFilter } = useMarketerFilter();
  const t = useTranslations("marketerProfile");
  const active = searchParams.get("discount") === "1";

  return (
    <AccordionItem value="discount" className="border-b-0! scrollbar-hide">
      <AccordionTrigger className={"font-bold flex items-center"}>
        {t("filterDiscounts")}
        {active && (
          <p className="text-green ms-1 flex items-center">
            {t("active")} <DotIcon />
          </p>
        )}
      </AccordionTrigger>
      <AccordionContent>
        <label className="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-2">
          <input
            type="checkbox"
            checked={active}
            onChange={(e) => setFilter("discount", e.target.checked ? "1" : "")}
            className="size-4 accent-main"
          />
          <span className="min-w-0 flex-1 truncate">{t("discountedOnly")}</span>
          <span className="text-xs text-gray">{count}</span>
        </label>
      </AccordionContent>
    </AccordionItem>
  );
};

export default FilterSidebar;
