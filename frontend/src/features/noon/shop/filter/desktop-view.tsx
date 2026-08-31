import { Accordion } from "@/src/components/ui/accordion";
import { Facets } from "@/src/features/noon/shop/types";
import FacetFilter from "./sections/facet-filter";
import PriceFilter from "./sections/price";

type FilterSidebarProps = {
  facets?: Facets | null;
};

const FilterSidebar = ({ facets }: FilterSidebarProps) => {
  const attributes = facets?.attributes ?? [];
  const defaultValue = [
    "price",
    ...attributes.map((attribute) => attribute.code),
  ];

  return (
    <Accordion multiple defaultValue={defaultValue}>
      <PriceFilter priceRange={facets?.price_range} />
      {attributes.map((attribute) => (
        <FacetFilter key={attribute.id} attribute={attribute} />
      ))}
    </Accordion>
  );
};

export default FilterSidebar;
