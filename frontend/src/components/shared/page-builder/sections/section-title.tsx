import { Button } from "@/src/components/ui/button";
import React from "react";

type Props = {
  title: string;
  showVewAllButton?: boolean;
};

const SectionTitle = ({ title, showVewAllButton }: Props) => {
  return (
    <div className="flex items-center justify-between my-4">
      <h2 className="text-light flex-1 font-bold text-lg md:text-xl xl:text-2xl">
        {title}
      </h2>
      {showVewAllButton && <Button variant={"outline"}>View All</Button>}
    </div>
  );
};

export default SectionTitle;
  