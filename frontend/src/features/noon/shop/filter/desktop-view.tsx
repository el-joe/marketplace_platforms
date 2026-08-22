import { Accordion } from "@/src/components/ui/accordion";

import FulfilledByFilter from "./sections/fulfilled-by";
import ArrivedByFilter from "./sections/arrived-by";
import CategoryFilter from "./sections/category";
import BrandFilter from "./sections/brand";
import PriceFilter from "./sections/price";
import DealsFilter from "./sections/deals";
import PriceDropFilter from "./sections/price-drop";
import ProductRatingFilter from "./sections/product-rating";
import ColourFilter from "./sections/colour";

const FILTER_SECTION_VALUES = [
  "fulfilled-by",
  "arrived-by",
  "category",
  "brand",
  "price",
  "deals",
  "price-drop",
  "rating",
  "colour",
];

const FilterSidebar = () => {
  return (
    <Accordion multiple defaultValue={FILTER_SECTION_VALUES}>
      <FulfilledByFilter />
      <ArrivedByFilter />
      <CategoryFilter />
      <BrandFilter />
      <PriceFilter />
      <DealsFilter />
      <PriceDropFilter />
      <ProductRatingFilter />
      <ColourFilter />
    </Accordion>
  );
};

export default FilterSidebar;
