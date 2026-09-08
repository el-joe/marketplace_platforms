"use client";
import { Button } from "@/src/components/ui/button";
import React from "react";
import useLocale from "@/src/hooks/use-locale";
import { Name } from "@/types/globals";
import { Link } from "@/i18n/navigation";

type Props = {
  title: string | Name;
  showVewAllButton?: boolean;
  viewAllUrl?: string | null;
};

const SectionTitle = ({ title, showVewAllButton, viewAllUrl }: Props) => {
  const locale = useLocale();

  const resolvedTitle =
    typeof title === "string"
      ? title
      : (title as Name)?.[locale] ?? (title as Name)?.en ?? "";

  return (
    <div className="flex items-center justify-between my-4">
      <h2 className="text-light flex-1 font-bold text-lg md:text-xl xl:text-2xl">
        {resolvedTitle}
      </h2>
      {showVewAllButton && viewAllUrl && (
        <Link href={viewAllUrl}>
          <Button variant={"outline"}>View All</Button>
        </Link>
      )}
    </div>
  );
};

export default SectionTitle;
