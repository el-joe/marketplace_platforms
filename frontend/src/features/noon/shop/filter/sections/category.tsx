"use client";

import { Accordion as AccordionPrimitive } from "@base-ui/react/accordion";
import { PlusIcon, MinusIcon } from "lucide-react";

import { Label } from "@/src/components/ui/label";

import FilterAccordionSection from "./filter-accordion-section";
import { categoryTree, type CategoryNode } from "../filter-data";
import { Checkbox } from "@/src/components/ui/base-inputs/checkbox";

const CategoryFilter = () => (
  <FilterAccordionSection value="category" title="Category">
    <CategoryNodeList nodes={categoryTree} />
  </FilterAccordionSection>
);

const CategoryNodeList = ({ nodes }: { nodes: CategoryNode[] }) => (
  <AccordionPrimitive.Root multiple className="flex w-full flex-col gap-1">
    {nodes.map((node) =>
      node.children?.length ? (
        <CategoryNodeAccordion key={node.id} node={node} />
      ) : (
        <CategoryLeaf key={node.id} node={node} />
      ),
    )}
  </AccordionPrimitive.Root>
);

const CategoryNodeAccordion = ({ node }: { node: CategoryNode }) => (
  <AccordionPrimitive.Item value={node.id} className="border-b-0!">
    <AccordionPrimitive.Header className="flex items-center gap-2">
      <PlusIcon className="size-3.5 shrink-0 text-muted-foreground group-aria-expanded/cat-trigger:hidden" />
      <MinusIcon className="hidden size-3.5 shrink-0 text-muted-foreground group-aria-expanded/cat-trigger:inline" />
      <AccordionPrimitive.Trigger
        data-slot="category-trigger"
        className="group/cat-trigger flex flex-1 cursor-pointer items-center justify-between gap-2 py-1.5 text-left text-sm font-medium outline-none"
      >
        <span>{node.label}</span>
      </AccordionPrimitive.Trigger>
    </AccordionPrimitive.Header>

    <AccordionPrimitive.Panel className="overflow-hidden data-open:animate-accordion-down data-closed:animate-accordion-up">
      <div className="h-(--accordion-panel-height) pl-6 data-ending-style:h-0 data-starting-style:h-0">
        <CategoryNodeList nodes={node.children!} />
      </div>
    </AccordionPrimitive.Panel>
  </AccordionPrimitive.Item>
);

const CategoryLeaf = ({ node }: { node: CategoryNode }) => (
  <Label className="flex items-center gap-2 py-1.5 font-normal cursor-pointer">
    <Checkbox />
    {node.label}
  </Label>
);

export default CategoryFilter;
