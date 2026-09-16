"use client";

import { useTranslations } from "next-intl";

import { Button } from "@/src/components/ui/button";

type SeeAllToggleProps = {
  expanded: boolean;
  totalCount: number;
  onToggle: () => void;
};

const SeeAllToggle = ({
  expanded,
  totalCount,
  onToggle,
}: SeeAllToggleProps) => {
  const t = useTranslations("shop");

  return (
    <Button
      type="button"
      variant="link"
      size="sm"
      className="h-auto self-start p-0 text-xs text-blue"
      onClick={onToggle}
    >
      {expanded ? t("seeLess") : t("seeAll", { count: totalCount })}
    </Button>
  );
};

export default SeeAllToggle;
