import FilterAccordionSection from "./filter-accordion-section";
import OptionsList from "./options-list";
import { fulfilledByOptions } from "../filter-data";

const FulfilledByFilter = () => (
  <FilterAccordionSection value="fulfilled-by" title="Fulfilled by">
    <OptionsList options={fulfilledByOptions} />
  </FilterAccordionSection>
);

export default FulfilledByFilter;
