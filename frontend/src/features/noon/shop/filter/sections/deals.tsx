import FilterAccordionSection from "./filter-accordion-section";
import OptionsList from "./options-list";
import { dealsOptions } from "../filter-data";

const DealsFilter = () => (
  <FilterAccordionSection value="deals" title="Deals">
    <OptionsList options={dealsOptions} />
  </FilterAccordionSection>
);

export default DealsFilter;
