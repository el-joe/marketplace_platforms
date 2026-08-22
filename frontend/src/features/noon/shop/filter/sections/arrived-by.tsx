import FilterAccordionSection from "./filter-accordion-section";
import OptionsList from "./options-list";
import { arrivedByOptions } from "../filter-data";

const ArrivedByFilter = () => (
  <FilterAccordionSection value="arrived-by" title="Arrived by">
    <OptionsList options={arrivedByOptions} />
  </FilterAccordionSection>
);

export default ArrivedByFilter;
