import HelpCard from "./help-card";
import HelpSheetHeader from "./help-sheet-header";
import type { HelpNode } from "../types";

interface NestedMenuViewProps {
  node: HelpNode;
  isRoot: boolean;
  greeting?: string;
  onSelect: (node: HelpNode) => void;
  onBack: () => void;
  onClose: () => void;
}

export default function NestedMenuView({
  node,
  isRoot,
  greeting,
  onSelect,
  onBack,
  onClose,
}: NestedMenuViewProps) {
  return (
    <div className="flex h-full flex-col">
      <HelpSheetHeader
        title={node.title}
        description={node.description}
        showBack={!isRoot}
        greeting={greeting}
        onBack={onBack}
        onClose={onClose}
      />

      <div className="flex-1 overflow-y-auto px-6 pb-6">
        <div className={isRoot ? "grid grid-cols-2 gap-3" : "flex flex-col gap-3"}>
          {node.children?.map((child) => (
            <HelpCard
              key={child.id}
              node={child}
              onClick={onSelect}
              variant={isRoot ? "grid" : "list"}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
