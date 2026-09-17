import { Accordion } from "@/src/components/ui/accordion";
import { Facets } from "@/src/features/noon/shop/types";
import FacetFilter from "./sections/facet-filter";
import PriceFilter from "./sections/price";
import useLocale from "@/src/hooks/use-locale";

type FilterSidebarProps = {
  facets?: Facets | null;
  locale: string;
};

const FilterSidebar = ({ facets, locale }: FilterSidebarProps) => {


  const attributes = facets?.attributes ?? [];
  const defaultValue = [
    "price",
    ...attributes.map((attribute) => attribute.code),
  ];

  return (
    <Accordion
      multiple
      defaultValue={defaultValue}
      dir={locale === "ar" ? "rtl" : "ltr"}
    >
      <PriceFilter priceRange={facets?.price_range} />
      {attributes.map((attribute) => (
        <FacetFilter key={attribute.id} attribute={attribute} />
      ))}
    </Accordion>
  );
};

export default FilterSidebar;
