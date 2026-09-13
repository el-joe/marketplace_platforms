import type { HelpFaqItem, HelpNode } from "../types";

export function faqItemToDetailNode(
  parent: HelpNode,
  item: HelpFaqItem,
): HelpNode {
  return {
    id: `${parent.id}__${item.id}`,
    title: item.question,
    description: "",
    icon: parent.icon,
    type: "action",
    viewType: "faq-detail",
    faqAnswer: item.answer,
    faqParentTitle: parent.title,
  };
}
