import { ChevronLeft, XIcon } from "lucide-react";
import type { HelpNode } from "../types";

interface FaqDetailViewProps {
  node: HelpNode;
  onBack: () => void;
  onClose: () => void;
}

export default function FaqDetailView({
  node,
  onBack,
  onClose,
}: FaqDetailViewProps) {
  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between gap-2 p-6 pb-4">
        <button
          type="button"
          onClick={onBack}
          aria-label="Back"
          className="flex size-8 cursor-pointer items-center justify-center rounded-full bg-white/60"
        >
          <ChevronLeft className="size-5" />
        </button>
        <p className="flex-1 truncate text-center font-semibold text-light">
          {node.faqParentTitle}
        </p>
        <button
          type="button"
          onClick={onClose}
          aria-label="Close"
          className="flex size-8 shrink-0 cursor-pointer items-center justify-center"
        >
          <XIcon className="size-5" />
        </button>
      </div>

      <div className="flex flex-1 flex-col justify-between overflow-y-auto px-6 pb-6">
        <div>
          <h1 className="text-2xl font-bold text-light">{node.title}</h1>
          <p className="mt-3 text-sm leading-relaxed text-gray">
            {node.faqAnswer}
          </p>
        </div>

        <div className="mt-6 text-center">
          <p className="font-bold text-light">Need more help?</p>
          <p className="mt-1 text-sm text-gray">
            If you still have any unresolved queries, please reach out to our{" "}
            <span className="font-semibold text-light">customer support</span>
          </p>
        </div>
      </div>
    </div>
  );
}
