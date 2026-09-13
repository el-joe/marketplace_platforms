import { FileTextIcon } from "lucide-react";
import HelpSheetHeader from "./help-sheet-header";
import { faqItemToDetailNode } from "../helpers/faq-item-to-node";
import type { HelpNode } from "../types";

interface FaqViewProps {
  node: HelpNode;
  onSelect: (node: HelpNode) => void;
  onBack: () => void;
  onClose: () => void;
}

export default function FaqView({
  node,
  onSelect,
  onBack,
  onClose,
}: FaqViewProps) {
  return (
    <div className="flex h-full flex-col">
      <HelpSheetHeader
        title={node.title}
        description={node.description}
        showBack
        onBack={onBack}
        onClose={onClose}
      />

      <div className="flex-1 overflow-y-auto px-6 pb-6">
        <div className="divide-y divide-gray-3 rounded-2xl bg-white px-4">
          {node.faqItems?.map((item) => (
            <button
              key={item.id}
              type="button"
              onClick={() => onSelect(faqItemToDetailNode(node, item))}
              className="flex w-full cursor-pointer items-start gap-3 py-4 text-start"
            >
              <FileTextIcon className="mt-0.5 size-5 shrink-0 text-gray" />
              <span className="flex-1">
                <span className="block font-bold text-light">
                  {item.question}
                </span>
                <span className="block text-sm text-gray">
                  {item.description}
                </span>
              </span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
