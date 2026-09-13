import { ChevronRight } from "lucide-react";
import type { HelpNode } from "../types";

interface HelpCardProps {
  node: HelpNode;
  onClick: (node: HelpNode) => void;
  variant?: "grid" | "list";
}

export default function HelpCard({
  node,
  onClick,
  variant = "grid",
}: HelpCardProps) {
  const Icon = node.icon;

  if (variant === "list") {
    return (
      <button
        type="button"
        onClick={() => onClick(node)}
        className="flex w-full cursor-pointer items-center gap-3 rounded-2xl bg-white p-4 text-start shadow-sm transition-colors hover:bg-gray-3"
      >
        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-3">
          <Icon className="size-5" />
        </span>
        <span className="flex-1">
          <span className="block font-bold text-light">{node.title}</span>
          {node.description && (
            <span className="block text-sm text-gray">
              {node.description}
            </span>
          )}
        </span>
        <ChevronRight className="size-5 shrink-0 text-gray" />
      </button>
    );
  }

  return (
    <button
      type="button"
      onClick={() => onClick(node)}
      className="flex h-full cursor-pointer flex-col items-start gap-3 rounded-2xl bg-white p-4 text-start shadow-sm transition-colors hover:bg-gray-3"
    >
      <Icon className="size-6" />
      <span>
        <span className="block font-bold text-light">{node.title}</span>
        {node.description && (
          <span className="block text-sm text-gray">{node.description}</span>
        )}
      </span>
    </button>
  );
}
