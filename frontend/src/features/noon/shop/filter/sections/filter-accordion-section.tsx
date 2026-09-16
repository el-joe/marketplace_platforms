import {
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from "@/src/components/ui/accordion";

type FilterAccordionSectionProps = {
  value: string;
  title: string;
  children: React.ReactNode;
};

const FilterAccordionSection = ({
  value,
  title,
  children,
}: FilterAccordionSectionProps) => (
  <AccordionItem value={value} className="border-b-0! scrollbar-hide">
    <AccordionTrigger className={"font-bold"}>{title}</AccordionTrigger>
    <AccordionContent>{children}</AccordionContent>
  </AccordionItem>
);

export default FilterAccordionSection;
