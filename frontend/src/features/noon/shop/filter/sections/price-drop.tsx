import FilterAccordionSection from "./filter-accordion-section";
import OptionsList from "./options-list";
import { priceDropOptions } from "../filter-data";

const PriceDropFilter = () => (
  <FilterAccordionSection value="price-drop" title="Price drop">
    <OptionsList options={priceDropOptions} />
  </FilterAccordionSection>
);

export default PriceDropFilter;
