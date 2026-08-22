"use client";

import { Button } from "@/src/components/ui/button";

type SeeAllToggleProps = {
  expanded: boolean;
  totalCount: number;
  onToggle: () => void;
};

const SeeAllToggle = ({ expanded, totalCount, onToggle }: SeeAllToggleProps) => (
  <Button
    type="button"
    variant="link"
    size="sm"
    className="h-auto self-start p-0 text-xs"
    onClick={onToggle}
  >
    {expanded ? "See less" : `See all (${totalCount})`}
  </Button>
);

export default SeeAllToggle;
